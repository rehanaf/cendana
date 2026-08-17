<?php

namespace App\Http\Controllers;

use App\Models\Sop;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SopController extends Controller
{
    public function index(): View
    {
        $sops = Sop::query()
            ->where(function ($query): void {
                $query->where('access_type', Sop::ACCESS_PUBLIC)
                    ->orWhere('is_public', true);
            })
            ->orderBy('title')
            ->get();

        return view('sop.public', [
            'sops' => $sops,
            'adminUrl' => url('/admin'),
        ]);
    }

    public function viewPdf(Sop $sop): BinaryFileResponse
    {
        if (! $sop->isPublic()) {
            $user = auth()->user();
            $allowed = $user && (
                $user->isAdmin()
                || $sop->isAllDivisions()
                || ($sop->isSpecificDivision() && $user->role_id && $user->role_id === $sop->role_id)
                || $user->hasPermission('view_sops')
            );

            abort_unless($allowed, 403);
        }

        $path = Storage::disk('local')->path($sop->file_path);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($sop->file_path) . '"',
        ]);
    }

    public function download(Sop $sop): BinaryFileResponse
    {
        return $this->viewPdf($sop);
    }
}
