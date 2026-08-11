<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

trait HasResourcePermissions
{
    protected static function getPermissionName(string $action): string
    {
        return $action . '_' . static::$permissionPrefix;
    }

    protected static function permissionResponse(string $action): Response
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return Response::allow();
        }

        return $user?->hasPermission(static::getPermissionName($action))
            ? Response::allow()
            : Response::deny();
    }

    public static function canViewAny(): bool
    {
        return static::getViewAnyAuthorizationResponse()->allowed();
    }

    public static function canCreate(): bool
    {
        return static::getCreateAuthorizationResponse()->allowed();
    }

    public static function canEdit(Model $record): bool
    {
        return static::getEditAuthorizationResponse($record)->allowed();
    }

    public static function canDelete(Model $record): bool
    {
        return static::getDeleteAuthorizationResponse($record)->allowed();
    }

    public static function canDeleteAny(): bool
    {
        return static::getDeleteAnyAuthorizationResponse()->allowed();
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return static::permissionResponse('view');
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return static::permissionResponse('create');
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::permissionResponse('edit');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::permissionResponse('delete');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return static::permissionResponse('delete');
    }
}
