<?php

declare(strict_types=1);

namespace App\Controller\Admin\Crud;

use App\Entity\User;
use App\Entity\UserBan;
use App\Service\BanEscalationService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use App\Repository\UserBanRepository;

final class UserBanCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly UserBanRepository $userBanRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return UserBan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->setIndexSectionTitle($crud, 'admin.menu.bans')
            ->setEntityLabelInSingular($this->t('admin.crud.ban.entity_singular'))
            ->setEntityLabelInPlural($this->t('admin.crud.ban.entity_plural'))
            ->setEntityPermission(User::ROLE_MODERATOR)
            ->setSearchFields(['reason'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, User::ROLE_ADMIN);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add($this->entityFilter('bannedUser', $this->t('admin.crud.ban.field_banned_user')))
            ->add($this->entityFilter('relatedGroup', $this->t('admin.crud.ban.field_group')))
            ->add(TextFilter::new('reason', $this->t('admin.crud.ban.field_reason')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.ban.fieldset_ban'));
        yield $this->entityAssociation('bannedUser', $this->t('admin.crud.ban.field_banned_user'));
        yield TextareaField::new('reason', $this->t('admin.crud.ban.field_reason'))->hideOnIndex();
        yield TextField::new('reason', $this->t('admin.crud.ban.field_reason'))
            ->onlyOnIndex()
            ->formatValue(static fn (?string $value): string => null !== $value && mb_strlen($value) > 60
                ? mb_substr($value, 0, 60).'…'
                : (string) $value);

        yield FormField::addFieldset($this->t('admin.crud.ban.fieldset_context'));
        yield TextField::new('relatedGroupLabel', $this->t('admin.crud.ban.field_group'))
            ->formatValue(function (?string $value, UserBan $ban): string {
                $group = $ban->getRelatedGroup();
                if (null !== $group) {
                    return $group->getAdminLabel();
                }

                return $this->t('admin.crud.ban.platform');
            });
        yield IntegerField::new('escalationStep', $this->t('admin.crud.ban.field_escalation'))
            ->formatValue(function (?int $value, UserBan $ban): string {
                if (null === $ban->getRelatedGroup()) {
                    return $this->notAvailable();
                }

                $createdAt = $ban->getCreatedAt();
                if (null === $createdAt) {
                    return $this->notAvailable();
                }

                $step = $this->userBanRepository->countBansForUserAtOrBefore($ban->getBannedUser(), $createdAt);

                return $this->t('admin.crud.ban.escalation_step', [
                    '%step%' => (string) min($step, BanEscalationService::MAX_BANS_BEFORE_DELETION),
                    '%max%' => (string) BanEscalationService::MAX_BANS_BEFORE_DELETION,
                ]);
            });
        yield $this->entityAssociation('author', $this->t('admin.crud.ban.field_author'));
        yield $this->adminDateTimeField('createdAt', $this->t('admin.crud.common.created_at'), $pageName);
        yield $this->adminDateTimeField('endsAt', $this->t('admin.crud.ban.field_ends_at'), $pageName)
            ->formatValue(function (?\DateTimeImmutable $value, UserBan $ban): string {
                if (!$ban->isActiveAt()) {
                    return $this->t('admin.crud.ban.status.expired');
                }

                if (null === $value) {
                    return $this->t('admin.crud.ban.status.active_open');
                }

                return $this->t('admin.crud.ban.status.active_until', [
                    '%date%' => $this->formatAdminDateTime($value),
                ]);
            });
    }
}
