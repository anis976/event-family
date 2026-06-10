<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Règles d'action staff sur les comptes utilisateurs (admin EasyAdmin).
 *
 * Modérateur : édition utilisateurs simples + soi-même ; ban/déban utilisateurs simples uniquement.
 * Super-modérateur : édition/suppression de tous les comptes sauf administrateur ;
 *   ban/déban utilisateurs simples et modérateurs (pas super-modérateur, pas admin, pas soi-même).
 * Administrateur : toutes les actions sauf auto-suspension.
 */
final class AdminUserPolicyService
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function isAdminAccount(User $user): bool
    {
        return \in_array(User::ROLE_ADMIN, $user->getRoles(), true);
    }

    public function getTargetStaffTier(User $target): int
    {
        $roles = $target->getRoles();
        if (\in_array(User::ROLE_ADMIN, $roles, true)) {
            return 3;
        }
        if (\in_array(User::ROLE_SUPER_MODERATOR, $roles, true)) {
            return 2;
        }
        if (\in_array(User::ROLE_MODERATOR, $roles, true)) {
            return 1;
        }

        return 0;
    }

    public function canEdit(User $actor, User $target): bool
    {
        if ($this->isAdminAccount($target) && !$this->authorizationChecker->isGranted(User::ROLE_ADMIN)) {
            return false;
        }

        if ($this->authorizationChecker->isGranted(User::ROLE_ADMIN)) {
            return true;
        }

        if ($this->authorizationChecker->isGranted(User::ROLE_SUPER_MODERATOR)) {
            return !$this->isAdminAccount($target);
        }

        if ($this->authorizationChecker->isGranted(User::ROLE_MODERATOR)) {
            if ($actor->getId() === $target->getId()) {
                return true;
            }

            return 0 === $this->getTargetStaffTier($target);
        }

        return false;
    }

    public function canDelete(User $actor, User $target): bool
    {
        if ($actor->getId() === $target->getId()) {
            return false;
        }

        if (!$this->canEdit($actor, $target)) {
            return false;
        }

        if ($this->authorizationChecker->isGranted(User::ROLE_ADMIN)) {
            return true;
        }

        return $this->authorizationChecker->isGranted(User::ROLE_SUPER_MODERATOR)
            && !$this->isAdminAccount($target);
    }

    public function canBanOrUnban(User $actor, User $target): bool
    {
        if ($actor->getId() === $target->getId()) {
            return false;
        }

        if ($this->isAdminAccount($target) && !$this->authorizationChecker->isGranted(User::ROLE_ADMIN)) {
            return false;
        }

        if ($this->authorizationChecker->isGranted(User::ROLE_ADMIN)) {
            return true;
        }

        $targetTier = $this->getTargetStaffTier($target);

        if ($this->authorizationChecker->isGranted(User::ROLE_SUPER_MODERATOR)) {
            return $targetTier <= 1;
        }

        if ($this->authorizationChecker->isGranted(User::ROLE_MODERATOR)) {
            return 0 === $targetTier;
        }

        return false;
    }

    public function getEditDenialKey(User $actor, User $target): string
    {
        if ($this->isAdminAccount($target)) {
            return 'admin.crud.user.error_admin_target';
        }

        if ($this->getTargetStaffTier($target) > 0) {
            return 'admin.crud.user.error_edit_staff_target';
        }

        return 'admin.access.denied';
    }

    public function getBanDenialKey(User $actor, User $target): string
    {
        if ($this->isAdminAccount($target)) {
            return 'admin.crud.user.error_admin_target';
        }

        $targetTier = $this->getTargetStaffTier($target);

        if ($targetTier >= 2) {
            return 'admin.crud.user.error_super_moderator_target';
        }

        if ($targetTier >= 1) {
            return 'admin.crud.user.error_staff_target';
        }

        return 'admin.access.denied';
    }

    public function getDeleteDenialKey(User $target): string
    {
        if ($this->isAdminAccount($target)) {
            return 'admin.crud.user.error_admin_target';
        }

        return 'admin.access.denied';
    }
}
