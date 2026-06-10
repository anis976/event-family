<?php

declare(strict_types=1);

namespace App\Controller\Admin\Crud;

use App\Admin\EasyAdmin\EntityLabels;
use App\Entity\Group;
use App\Entity\User;
use App\Service\GroupOwnerTransferService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

final class GroupCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly GroupOwnerTransferService $groupOwnerTransfer,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Group::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->setIndexSectionTitle($crud, 'admin.menu.groups')
            ->setEntityLabelInSingular($this->t('admin.crud.group.entity_singular'))
            ->setEntityLabelInPlural($this->t('admin.crud.group.entity_plural'))
            ->setEntityPermission(User::ROLE_MODERATOR)
            ->setSearchFields(['name', 'familyName', 'description'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::DELETE, User::ROLE_ADMIN)
            ->setPermission(Action::BATCH_DELETE, User::ROLE_ADMIN);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', $this->t('admin.crud.group.field_name')))
            ->add(TextFilter::new('familyName', $this->t('admin.crud.group.field_family_name')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.group.fieldset_group'));
        yield TextField::new('name', $this->t('admin.crud.group.field_name'));
        yield TextField::new('familyName', $this->t('admin.crud.group.field_family_name'));
        yield TextareaField::new('description', $this->t('admin.crud.group.field_description'))
            ->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.group.fieldset_owners'));
        yield $this->entityAssociation('owner', $this->t('admin.crud.group.field_owner'))
            ->formatValue(fn (mixed $value): string => null === $value
                ? $this->t('admin.crud.group.no_owner')
                : EntityLabels::format($value, $this->notAvailable()))
            ->setHelp($this->t('admin.crud.group.help_owner_reassign'));
        yield $this->entityAssociation('author', $this->t('admin.crud.group.field_author'))
            ->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.group.fieldset_system_notice'))->onlyOnDetail();

        if ($this->isGranted(User::ROLE_ADMIN)) {
            yield TextareaField::new('systemNoticeContent', $this->t('admin.crud.group.field_system_notice'))
                ->hideOnIndex()
                ->onlyOnForms();
        }
        yield $this->adminDateTimeField('systemNoticeUpdatedAt', $this->t('admin.crud.group.field_system_notice_updated'), $pageName)
            ->hideOnForm()
            ->hideOnIndex();

        yield $this->adminDateTimeField('createdAt', $this->t('admin.crud.common.created_at'), $pageName)->hideOnForm()->hideOnIndex();
        yield $this->adminDateTimeField('updatedAt', $this->t('admin.crud.common.updated_at'), $pageName)->hideOnForm()->hideOnIndex();
    }

    /**
     * @param Group $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $owner = $entityInstance->getOwner();

        parent::persistEntity($entityManager, $entityInstance);

        if (null === $owner) {
            return;
        }

        try {
            $this->groupOwnerTransfer->bindOwnerOnGroupCreate($entityInstance, $owner);
            $entityManager->flush();
        } catch (\DomainException $exception) {
            throw $this->translateDomainException($exception);
        }
    }

    /**
     * @param Group $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $existing = $entityManager->find(Group::class, $entityInstance->getId());
        $previousOwner = $existing?->getOwner();
        $newOwner = $entityInstance->getOwner();

        if (null !== $existing && !$this->isGranted(User::ROLE_ADMIN)) {
            $entityInstance->setSystemNoticeContent($existing->getSystemNoticeContent());
            $entityInstance->setSystemNoticeUpdatedAt($existing->getSystemNoticeUpdatedAt());
        }

        try {
            if ($previousOwner?->getId() !== $newOwner?->getId()) {
                if (null === $newOwner) {
                    $this->groupOwnerTransfer->clearOwnership($entityInstance, $previousOwner);
                } else {
                    $this->groupOwnerTransfer->transferOwnership($entityInstance, $newOwner, $previousOwner);
                }
            }
        } catch (\DomainException $exception) {
            throw $this->translateDomainException($exception);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
