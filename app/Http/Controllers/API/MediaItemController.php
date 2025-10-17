<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\MediaDownload;
use App\Models\MediaItem;
use App\Models\MediaTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

use Illuminate\Support\Str;

class MediaItemController extends Controller
{
    // GET /api/media
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $search = $request->string('search')->toString();

        $q = MediaItem::query()
            ->with(['uploader:id,name', 'targets:id,media_id,target_type,group_id,user_id'])
            ->visibleTo($user)
            ->when($search, fn ($qq) =>
                $qq->where(function ($w) use ($search) {
                    $w->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                })
            )
            ->orderByDesc('created_at');

        return $q->paginate($request->integer('per_page', 15));
    }

    // POST /api/media
    // Form-data: file, title, description?, scope ('all'|'groups'|'users'), targets? []
    public function store(Request $request)
    {
        try{
            if ($f = $request->file('file')) {
                logger()->error('Upload debug', [
                    'is_valid'  => $f->isValid(),
                    'err_code'  => $f->getError(),        // <- número UPLOAD_ERR_*
                    'err_msg'   => $f->getErrorMessage(), // <- texto útil (partial, cant write, etc.)
                    'tmp_dir'   => $f->getPath(),         // normalmente /tmp
                    'size'      => $f->getSize(),
                    'mime'      => $f->getClientMimeType(),
                ]);
            } else {
                logger()->error('Upload debug: no llegó el campo "file" (multipart/form-data faltante)');
            }
            
            $validated = $request->validate([
                'title'       => ['required','string','max:180'],
                'description' => ['nullable','string'],
                'scope'       => ['required','in:all,groups,users'],
                'file'        => ['required', function($attr,$value,$fail){
                                    if (! $value || ! $value->isValid()) {
                                        $fail($value?->getErrorMessage() ?? 'Archivo no válido');
                                    }
                                }], // 500MB
                'targets'     => ['array'], // [{ target_type, group_id|user_id }]
                'targets.*.target_type' => ['required_with:targets','in:group,user'],
                'targets.*.group_id'    => ['nullable','integer','exists:groups,id'],
                'targets.*.user_id'     => ['nullable','integer','exists:users,id'],
            ]);

            $file = $request->file('file');
            $path = $file->store('media'); // usa el disco default; configura si necesitas otro

            $media = null;
            DB::transaction(function () use ($request, $validated, $file, $path, &$media) {
                /** @var User $user */
                $user = $request->user();

                $media = MediaItem::create([
                    'uploader_user_id' => $user->id,
                    'title'            => $validated['title'],
                    'description'      => $validated['description'] ?? null,
                    'file_path'        => $path,
                    'mime_type'        => $file->getClientMimeType() ?? $file->getMimeType(),
                    'file_size_bytes'  => $file->getSize(),
                    'checksum_sha256'  => hash_file('sha256', $file->getRealPath()),
                    'scope'            => $validated['scope'],
                ]);

                // targets (opcionales). En 'all' normalmente no se envían.
                if (!empty($validated['targets'])) {
                    $toInsert = [];
                    foreach ($validated['targets'] as $t) {
                        if ($t['target_type'] === 'group') {
                            $toInsert[] = [
                                'media_id'    => $media->id,
                                'target_type' => 'group',
                                'group_id'    => $t['group_id'] ?? null,
                                'user_id'     => null,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ];
                        } else {
                            $toInsert[] = [
                                'media_id'    => $media->id,
                                'target_type' => 'user',
                                'group_id'    => null,
                                'user_id'     => $t['user_id'] ?? null,
                                'created_at'  => now(),
                                'updated_at'  => now(),
                            ];
                        }
                    }
                    MediaTarget::insert($toInsert);
                }
            });

            return response()->json(
                $media->load(['uploader:id,name','targets']),
                201
            );
        } catch (ValidationException $e) {
            return response()->json([
                'response_code' => 422,
                'status'        => 'error',
                'message'       => 'La validación ha fallado',
                'errors'        => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Announcement store error: ' . $e->getMessage());

            return response()->json([
                'response_code' => 500,
                'status'        => 'error',
                'message'       => 'Error interno del servidor: ' . $e,
            ], 500);
        }
    }

    // GET /api/media/{media}
    public function show(Request $request, MediaItem $media)
    {
        $this->authorizeView($request->user(), $media);

        return $media->load([
            'uploader:id,name,avatar_path',
            'targets.group:id,name,grade,section,code',
            'targets.user:id,name,avatar_path',
            'downloads' => fn ($q) => $q->latest()->limit(10),
        ]);
    }

    // PUT/PATCH /api/media/{media}
    public function update(Request $request, MediaItem $media)
    {
        $this->authorizeManage($request->user(), $media);

        $validated = $request->validate([
            'title'       => ['sometimes','string','max:180'],
            'description' => ['sometimes','nullable','string'],
            'scope'       => ['sometimes','in:all,groups,users'],
            'file'        => ['sometimes','file','max:512000'],
            'targets'     => ['sometimes','array'],
            'targets.*.target_type' => ['required_with:targets','in:group,user'],
            'targets.*.group_id'    => ['nullable','integer','exists:groups,id'],
            'targets.*.user_id'     => ['nullable','integer','exists:users,id'],
        ]);

        DB::transaction(function () use ($request, $media, $validated) {
            if (array_key_exists('title', $validated))        $media->title = $validated['title'];
            if (array_key_exists('description', $validated))  $media->description = $validated['description'];
            if (array_key_exists('scope', $validated))        $media->scope = $validated['scope'];

            if ($request->hasFile('file')) {
                // elimina el archivo anterior si quieres
                if ($media->file_path && Storage::exists($media->file_path)) {
                    Storage::delete($media->file_path);
                }
                $file = $request->file('file');
                $path = $file->store('media');

                $media->file_path       = $path;
                $media->mime_type       = $file->getClientMimeType() ?? $file->getMimeType();
                $media->file_size_bytes = $file->getSize();
                $media->checksum_sha256 = hash_file('sha256', $file->getRealPath());
            }

            $media->save();

            if (array_key_exists('targets', $validated)) {
                // resync: borra y vuelve a crear
                $media->targets()->delete();

                if (!empty($validated['targets'])) {
                    $toInsert = [];
                    foreach ($validated['targets'] as $t) {
                        $toInsert[] = [
                            'media_id'    => $media->id,
                            'target_type' => $t['target_type'],
                            'group_id'    => $t['target_type'] === 'group' ? ($t['group_id'] ?? null) : null,
                            'user_id'     => $t['target_type'] === 'user'  ? ($t['user_id'] ?? null)  : null,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ];
                    }
                    MediaTarget::insert($toInsert);
                }
            }
        });

        return $media->load(['uploader:id,name','targets']);
    }

    // DELETE /api/media/{media}
    public function destroy(Request $request, MediaItem $media)
    {
        $this->authorizeManage($request->user(), $media);

        // elimina archivo físico
        if ($media->file_path && Storage::exists($media->file_path)) {
            Storage::delete($media->file_path);
        }

        $media->delete();

        return response()->noContent();
    }

    // GET /api/media/{media}/download
    public function download(Request $request, MediaItem $media): StreamedResponse
    {
        $this->authorizeView($request->user(), $media);

        // registra auditoría
        MediaDownload::create([
            'media_id'      => $media->id,
            'user_id'       => $request->user()->id,
            'downloaded_at' => now(),
            'ip_address'    => $request->ip(),
        ]);

        abort_unless(Storage::exists($media->file_path), 404, 'Archivo no encontrado');

        $filename = str($media->title)->slug('_').'.'.pathinfo($media->file_path, PATHINFO_EXTENSION);

        return Storage::download($media->file_path, $filename, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    // GET /api/media/{media}/thumbnail?size=sm|md|lg
    public function thumbnail(Request $request, MediaItem $media)
    {
        $this->authorizeView($request->user(), $media);

        $size = $request->get('size', 'sm');
        $sizes = [
            'sm' => 160,   // cards pequeñas
            'md' => 320,   // grid mediano
            'lg' => 640,   // detalle
        ];
        $max = $sizes[$size] ?? 160;

        $cachePath = "thumbnails/{$media->id}/{$size}.jpg";
        if (Storage::exists($cachePath)) {
            return $this->thumbResponse($cachePath, $media);
        }

        // Intenta generar
        $mime = $media->mime_type;

        // 1) Si es imagen, redimensiona
        if (Str::startsWith($mime, 'image/')) {
            if (Storage::exists($media->file_path)) {
                //$img = Image::read(Storage::path($media->file_path))
                //            ->scaleDown(width: $max, height: $max);
                $manager = new ImageManager(new Driver());
$img = $manager->read(Storage::path($media->file_path))->scaleDown(width: $max, height: $max);
                Storage::put($cachePath, (string) $img->toJpeg(80));
                return $this->thumbResponse($cachePath, $media);
            }
        }

        // 2) Si es video, intenta poster con ffmpeg (si está disponible)
        if (Str::startsWith($mime, 'video/')) {
            $ffmpeg = trim((string) @shell_exec('command -v ffmpeg'));
            if ($ffmpeg && Storage::exists($media->file_path)) {
                $src = Storage::path($media->file_path);
                $tmp = storage_path("app/thumbnails/{$media->id}/poster.jpg");
                @mkdir(dirname($tmp), 0777, true);
                // Extrae un frame al segundo 1
                @shell_exec("$ffmpeg -y -ss 00:00:01 -i ".escapeshellarg($src)." -frames:v 1 ".escapeshellarg($tmp)." 2>/dev/null");
                if (file_exists($tmp)) {
                    $img = Image::read($tmp)->scaleDown(width: $max, height: $max);
                    Storage::put($cachePath, (string) $img->toJpeg(80));
                    @unlink($tmp);
                    return $this->thumbResponse($cachePath, $media);
                }
            }
        }

        // 3) Si es PDF, intenta rasterizar portada con Imagick
        if ($mime === 'application/pdf') {
            if (class_exists(\Imagick::class) && Storage::exists($media->file_path)) {
                try {
                    $pdf = new \Imagick();
                    $pdf->setResolution(144, 144);
                    $pdf->readImage(Storage::path($media->file_path).'[0]'); // página 1
                    $pdf->setImageFormat('jpeg');
                    $img = Image::read($pdf->getImageBlob())->scaleDown(width: $max, height: $max);
                    Storage::put($cachePath, (string) $img->toJpeg(80));
                    $pdf->clear();
                    $pdf->destroy();
                    return $this->thumbResponse($cachePath, $media);
                } catch (\Throwable $e) {
                    // sigue a fallback
                }
            }
        }

        // 4) Fallback por tipo
        $fallbackSvg = $this->fallbackIconSvg($mime); // SVG pequeño por tipo
        Storage::put($cachePath, $fallbackSvg);
        return response($fallbackSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => md5($fallbackSvg),
        ]);
    }

    // ---------- Helpers de autorización básicos ----------

    protected function authorizeView(User $user, MediaItem $media): void
    {
        $canView = MediaItem::query()
            ->whereKey($media->id)
            ->visibleTo($user)
            ->exists();

        abort_unless($canView, 403, 'No autorizado para ver este recurso.');
    }

    protected function authorizeManage(User $user, MediaItem $media): void
    {
        // Regla simple: el uploader puede administrar. Amplía con Policies/roles si lo necesitas.
        abort_unless($media->uploader_user_id === $user->id, 403, 'No autorizado para administrar este recurso.');
    }

    protected function thumbResponse(string $cachePath, MediaItem $media)
    {
        return response(
            Storage::get($cachePath),
            200,
            [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, max-age=604800',
                'ETag' => md5($media->id.'|'.$cachePath.'|'.Storage::lastModified($cachePath)),
            ]
        );
    }

    protected function fallbackIconSvg(string $mime): string
    {
        // Devuelve un SVG liviano según familia MIME (image/video/pdf/audio/zip/doc/etc.)
        $label = match (true) {
            str_starts_with($mime, 'image/') => 'IMG',
            str_starts_with($mime, 'video/') => 'VID',
            str_starts_with($mime, 'audio/') => 'AUD',
            $mime === 'application/pdf'      => 'PDF',
            str_contains($mime, 'zip')       => 'ZIP',
            str_contains($mime, 'excel')     => 'XLS',
            str_contains($mime, 'word')      => 'DOC',
            str_contains($mime, 'powerpoint')=> 'PPT',
            default                          => 'FILE',
        };

        // SVG cuadrado simple (tailwind-friendly)
        return <<<SVG
    <?xml version="1.0" encoding="UTF-8"?>
    <svg width="160" height="160" viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
    <rect x="8" y="8" width="144" height="144" rx="20" fill="#f3f4f6" stroke="#e5e7eb"/>
    <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="36" fill="#6b7280">{$label}</text>
    </svg>
    SVG;
    }
}
