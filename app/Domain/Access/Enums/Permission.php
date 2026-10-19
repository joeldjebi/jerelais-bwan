<?php

namespace App\Domain\Access\Enums;

/**
 * Permissions élémentaires (Annexe A — matrice des droits).
 * Le périmètre « sa commune » est appliqué en plus par les Policies et le CommuneScope.
 */
enum Permission: string
{
    case ConsoleAccess = 'console.access';
    case CandidatureApply = 'candidature.apply';
    case RelaisSubmit = 'relais.submit';
    case RelaisViewOwn = 'relais.view-own';
    case RelaisViewPending = 'relais.view-pending';
    case RelaisValidate = 'relais.validate';
    case RelaisArbitrate = 'relais.arbitrate';
    case RelaisExportUnvalidated = 'relais.export-unvalidated';
    case CandidaturesValidate = 'candidatures.validate';
    case IdentityView = 'identity.view';
    case MediasDownloadOriginal = 'medias.download-original';
    case AppelsCreate = 'appels.create';
    case AppelsCreateLocal = 'appels.create-local';
    case RelaisProfilesSuspend = 'relais-profiles.suspend';
    case SettingsManage = 'settings.manage';
    case TerritoireManage = 'territoire.manage';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';
    case ReportsView = 'reports.view';
}
