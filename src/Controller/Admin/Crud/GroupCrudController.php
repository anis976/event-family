<?php

declare(strict_types=1);

namespace App\Controller\Admin\Crud;

use App\Admin\EasyAdmin\EntityLabels;
use App\Entity\Group;
use App\Entity\User;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Service\GroupOwnerTransferService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class GroupCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly GroupOwnerTransferService $groupOwnerTransfer,
        private readonly GroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly Environment $twig,
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
            ->setDefaultSort(['id' => 'DESC'])
            ->overrideTemplates([
                'crud/detail' => 'admin/crud/group/detail.html.twig',
                'crud/edit' => 'admin/crud/group/edit.html.twig',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::DELETE, User::ROLE_ADMIN)
            ->setPermission(Action::BATCH_DELETE, User::ROLE_ADMIN);
    }

    public function new(AdminContext $context): KeyValueStore|Response
    {
        try {
            return parent::new($context);
        } catch (\DomainException $exception) {
            return $this->redirectWithOwnerError($exception);
        }
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        try {
            return parent::edit($context);
        } catch (\DomainException $exception) {
            $entity = $context->getEntity()->getInstance();

            return $this->redirectWithOwnerError(
                $exception,
                $entity instanceof Group ? $entity->getId() : null,
            );
        }
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

        if (null !== $owner) {
            $entityInstance->setOwner(null);
        }

        parent::persistEntity($entityManager, $entityInstance);

        if (null === $owner) {
            return;
        }

        try {
            $this->groupOwnerTransfer->bindOwnerOnGroupCreateFromAdmin($entityInstance, $owner);
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
        $groupId = $entityInstance->getId();
        if (null === $groupId) {
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        // Valeur en base : $entityInstance est déjà modifié par le formulaire EasyAdmin.
        $previousOwnerId = $this->groupRepository->findOwnerIdForGroup($groupId);
        $newOwner = $entityInstance->getOwner();
        $newOwnerId = $newOwner?->getId();

        if (!$this->isGranted(User::ROLE_ADMIN)) {
            $originalNotice = $entityManager->createQueryBuilder()
                ->select('g.systemNoticeContent', 'g.systemNoticeUpdatedAt')
                ->from(Group::class, 'g')
                ->andWhere('g.id = :id')
                ->setParameter('id', $groupId)
                ->getQuery()
                ->getOneOrNullResult();

            if (\is_array($originalNotice)) {
                $entityInstance->setSystemNoticeContent($originalNotice['systemNoticeContent']);
                $entityInstance->setSystemNoticeUpdatedAt($originalNotice['systemNoticeUpdatedAt']);
            }
        }

        try {
            if ($previousOwnerId !== $newOwnerId) {
                $previousOwner = null !== $previousOwnerId
                    ? $entityManager->find(User::class, $previousOwnerId)
                    : null;

                if (null === $newOwner) {
                    $this->groupOwnerTransfer->clearOwnership($entityInstance, $previousOwner);
                } else {
                    $this->groupOwnerTransfer->assignOwnerFromAdmin($entityInstance, $newOwner, $previousOwner);
                }
            }
        } catch (\DomainException $exception) {
            throw $this->translateDomainException($exception);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $context = $this->getContext();
        if (
            null !== $context
            && \in_array($context->getCrud()->getCurrentPage(), [Crud::PAGE_DETAIL, Crud::PAGE_EDIT], true)
        ) {
            $entity = $context->getEntity()->getInstance();
            if ($entity instanceof Group) {
                $responseParameters->set('ef_group_members_html', $this->renderMembersDetailHtml($entity));
                $responseParameters->set('ef_group_members_title', 'admin.crud.group.fieldset_members');
                $responseParameters->set('ef_group_members_hint', 'admin.crud.group.members_hint');
            }
        }

        return parent::configureResponseParameters($responseParameters);
    }

    private function redirectWithOwnerError(\DomainException $exception, ?int $groupId = null): Response
    {
        $this->addFlash('danger', $exception->getMessage());

        $urlGenerator = $this->container->get(AdminUrlGenerator::class)
            ->setController(self::class);

        if (null !== $groupId) {
            $urlGenerator->setAction(Action::EDIT)->setEntityId($groupId);
        } else {
            $urlGenerator->setAction(Action::NEW);
        }

        return $this->redirect($urlGenerator->generateUrl());
    }

    private function renderMembersDetailHtml(Group $group): string
    {
        return $this->twig->render('admin/crud/group/_members_detail.html.twig', [
            'members' => $this->groupMemberRepository->findAllByGroupOrdered($group),
            'owner_id' => $group->getOwner()?->getId(),
            'group_id' => $group->getId(),
            'can_manage_moderator' => true,
        ]);
    }
}
