<?php

declare(strict_types=1);

namespace App\Controller\Admin\Crud;

use App\Entity\User;
use App\Enum\AvatarVisibility;
use App\Repository\GroupRepository;
use App\Repository\UserBanRepository;
use App\Service\AdminPlatformBanService;
use App\Service\BanEscalationService;
use App\Service\EmailVerificationService;
use App\Service\GroupOwnerTransferService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class UserCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly EmailVerificationService $emailVerification,
        private readonly GroupRepository $groupRepository,
        private readonly GroupOwnerTransferService $groupOwnerTransfer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly AdminPlatformBanService $platformBanService,
        private readonly UserBanRepository $userBanRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->setIndexSectionTitle($crud, 'admin.menu.users')
            ->setEntityLabelInSingular($this->t('admin.crud.user.entity_singular'))
            ->setEntityLabelInPlural($this->t('admin.crud.user.entity_plural'))
            ->setEntityPermission(User::ROLE_MODERATOR)
            ->setSearchFields(['email', 'firstName', 'lastName', 'pseudo'])
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::DELETE, User::ROLE_SUPER_MODERATOR)
            ->setPermission(Action::BATCH_DELETE, User::ROLE_SUPER_MODERATOR);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email'))
            ->add(TextFilter::new('pseudo'))
            ->add(BooleanFilter::new('isVerified'))
            ->add(BooleanFilter::new('isBanned'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.user.fieldset_identity'));
        yield EmailField::new('email', $this->t('admin.crud.user.field_email'));
        yield TextField::new('firstName', $this->t('admin.crud.user.field_first_name'));
        yield TextField::new('lastName', $this->t('admin.crud.user.field_last_name'));
        yield TextField::new('pseudo', $this->t('admin.crud.user.field_pseudo'));

        yield FormField::addFieldset($this->t('admin.crud.user.fieldset_access'));
        if ($this->isGranted(User::ROLE_ADMIN)) {
            yield ChoiceField::new('roles', $this->t('admin.crud.user.field_roles'))
                ->setChoices([
                    $this->t('admin.crud.user.role_user') => User::ROLE_USER,
                    $this->t('admin.crud.user.role_moderator') => User::ROLE_MODERATOR,
                    $this->t('admin.crud.user.role_super_moderator') => User::ROLE_SUPER_MODERATOR,
                    $this->t('admin.crud.user.role_admin') => User::ROLE_ADMIN,
                ])
                ->setHelp($this->t('admin.crud.user.help_roles_admin_only'))
                ->allowMultipleChoices()
                ->renderAsBadges([
                    User::ROLE_USER => 'secondary',
                    User::ROLE_MODERATOR => 'info',
                    User::ROLE_SUPER_MODERATOR => 'primary',
                    User::ROLE_ADMIN => 'danger',
                ]);
        }
        yield TextField::new('plainPassword', $this->t('admin.crud.user.field_password'))
            ->setFormType(PasswordType::class)
            ->setFormTypeOptions([
                'mapped' => false,
                'hash_property_path' => 'password',
                'required' => Crud::PAGE_NEW === $pageName,
                'attr' => [
                    'placeholder' => Crud::PAGE_EDIT === $pageName
                        ? $this->t('ui.auth.placeholder.password_optional')
                        : $this->t('ui.auth.placeholder.password_new'),
                ],
            ])
            ->setHelp(Crud::PAGE_EDIT === $pageName
                ? $this->t('admin.crud.user.help_password_edit')
                : $this->t('admin.crud.user.help_password_new'))
            ->onlyOnForms()
            ->hideOnIndex();
        $verifiedField = BooleanField::new('isVerified', $this->t('admin.crud.user.field_verified'));
        if (Crud::PAGE_NEW === $pageName) {
            $verifiedField->setHelp($this->t('admin.crud.user.help_verified_new'));
        }
        yield $verifiedField;
        yield BooleanField::new('isBanned', $this->t('admin.crud.user.field_banned'))
            ->setHelp($this->t('admin.crud.user.help_banned'));
        yield TextareaField::new('platformBanReason', $this->t('admin.crud.user.field_ban_reason'))
            ->setFormTypeOption('mapped', false)
            ->setHelp($this->t('admin.crud.user.help_ban_reason'))
            ->setFormTypeOption('attr', ['rows' => 4])
            ->setFormTypeOption('constraints', [$this->createPlatformBanReasonConstraint()])
            ->onlyOnForms()
            ->hideOnIndex();
        yield TextareaField::new('platformBanReasonDisplay', $this->t('admin.crud.user.field_ban_reason'))
            ->onlyOnDetail()
            ->formatValue(function (?string $value, User $user): string {
                if (!$user->isBanned()) {
                    return $this->notAvailable();
                }

                $ban = $this->userBanRepository->findLatestActivePlatformBanForUser($user);

                return $ban?->getReason() ?? $this->notAvailable();
            });

        yield TextField::new('banSummary', $this->t('admin.crud.user.field_group_bans'))
            ->hideOnForm()
            ->formatValue(function (?string $value, User $user): string {
                $total = $this->userBanRepository->countTotalBansForUser($user);
                $active = \count($this->userBanRepository->findActiveBansForUser($user));

                return $this->t('admin.crud.user.ban_summary', [
                    '%total%' => (string) $total,
                    '%max%' => (string) BanEscalationService::MAX_BANS_BEFORE_DELETION,
                    '%active%' => (string) $active,
                ]);
            });

        yield FormField::addFieldset($this->t('admin.crud.user.fieldset_profile'))->onlyOnDetail();
        yield ChoiceField::new('avatarVisibility', $this->t('admin.crud.user.field_avatar_visibility'))
            ->setChoices([
                $this->t('admin.crud.user.avatar_public') => AvatarVisibility::Public,
                $this->t('admin.crud.user.avatar_private') => AvatarVisibility::Private,
            ])
            ->hideOnIndex();
        yield TextField::new('locale', $this->t('admin.crud.user.field_locale'))->hideOnIndex();
        yield $this->adminDateTimeField('lastLoginAt', $this->t('admin.crud.user.field_last_login'), $pageName)->hideOnForm();
        yield $this->adminDateTimeField('createdAt', $this->t('admin.crud.common.created_at'), $pageName)->hideOnForm();
        yield $this->adminDateTimeField('updatedAt', $this->t('admin.crud.common.updated_at'), $pageName)->hideOnForm();
        yield $this->adminDateTimeField('deletedAt', $this->t('admin.crud.common.deleted_at'), $pageName)->hideOnForm();
    }

    /**
     * @param User $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $shouldBan = $entityInstance->isBanned();
        $reason = $this->getPlatformBanReason();

        $entityInstance->setIsBanned(false);

        $this->enforceRolePolicyOnCreate($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);

        if ($shouldBan) {
            $this->handleBanStatusChange($entityInstance, false, true, $reason);
        }

        if (!$entityInstance->isVerified()) {
            $this->sendVerificationEmailAfterCreate($entityManager, $entityInstance);
        }
    }

    /**
     * @param User $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();
        $original = $unitOfWork->getOriginalEntityData($entityInstance);
        $wasBanned = (bool) ($original['isBanned'] ?? false);
        $willBeBanned = $entityInstance->isBanned();
        $reason = $this->getPlatformBanReason();

        $this->enforceRolePolicyOnUpdate($entityInstance, $original['roles'] ?? []);

        // isBanned est géré par AdminPlatformBanService après l'enregistrement standard.
        $entityInstance->setIsBanned($wasBanned);

        parent::updateEntity($entityManager, $entityInstance);

        $this->handleBanStatusChange($entityInstance, $wasBanned, $willBeBanned, $reason);
    }

    /**
     * @param User $entityInstance
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertCanDeleteTarget($entityInstance);

        if ([] !== $this->findBlockingOwnedGroups($entityInstance)) {
            throw new \LogicException('User deletion blocked by group ownership (handled in delete()).');
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function delete(AdminContext $context): KeyValueStore|Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        if ($response = $this->resolveDeleteBlockedResponse($user)) {
            return $response;
        }

        return parent::delete($context);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        $repository = $this->container->get('doctrine')->getRepository(User::class);
        $blockedUsers = [];

        foreach ($batchActionDto->getEntityIds() as $entityId) {
            $user = $repository->find($entityId);
            if (!$user instanceof User) {
                continue;
            }

            if (!$this->canDeleteTarget($user)) {
                $this->addFlash('danger', $this->t('admin.crud.user.error_admin_target'));

                return $this->redirectToUserIndex();
            }

            $blockingGroups = $this->findBlockingOwnedGroups($user);
            if ([] !== $blockingGroups) {
                $blockedUsers[] = [$user, $blockingGroups];
            }
        }

        if ([] !== $blockedUsers) {
            foreach ($blockedUsers as [$user, $blockingGroups]) {
                $this->flashOwnerDeletionBlocked($user, $blockingGroups);
            }

            return $this->redirectToUserIndex();
        }

        return parent::batchDelete($context, $batchActionDto);
    }

    private function sendVerificationEmailAfterCreate(EntityManagerInterface $entityManager, User $user): void
    {
        try {
            $this->emailVerification->sendVerificationEmail($user);
            $entityManager->flush();
            $this->addFlash('success', $this->t('admin.crud.user.verification_email_sent'));
        } catch (TransportExceptionInterface) {
            $this->addFlash('warning', $this->t('admin.crud.user.verification_email_failed'));
        }
    }

    private function normalizeRoles(User $user): void
    {
        $roles = array_values(array_unique(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => User::ROLE_USER !== $role,
        )));

        if ([] === $roles) {
            $roles = [User::ROLE_USER];
        }

        $user->setRoles($roles);
    }

    private function enforceRolePolicyOnCreate(User $user): void
    {
        if ($this->isGranted(User::ROLE_ADMIN)) {
            $this->normalizeRoles($user);

            return;
        }

        $user->setRoles([User::ROLE_USER]);
    }

    /**
     * @param mixed $originalRoles
     */
    private function enforceRolePolicyOnUpdate(User $user, mixed $originalRoles): void
    {
        if ($this->isGranted(User::ROLE_ADMIN)) {
            $this->normalizeRoles($user);

            return;
        }

        if (\is_array($originalRoles)) {
            /** @var list<string> $roles */
            $roles = array_values(array_filter(
                $originalRoles,
                static fn (mixed $role): bool => \is_string($role),
            ));
            $user->setRoles([] === $roles ? [User::ROLE_USER] : $roles);

            return;
        }

        $this->normalizeRoles($user);
    }

    /**
     * @return list<\App\Entity\Group>
     */
    private function findBlockingOwnedGroups(User $user): array
    {
        return $this->groupRepository->findOwnedGroupsWithOtherMembers($user);
    }

    private function resolveDeleteBlockedResponse(User $user): ?Response
    {
        $blockingGroups = $this->findBlockingOwnedGroups($user);
        if ([] === $blockingGroups) {
            return null;
        }

        $this->flashOwnerDeletionBlocked($user, $blockingGroups);

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::DETAIL)
                ->setEntityId($user->getId())
                ->generateUrl(),
        );
    }

    /**
     * @param list<\App\Entity\Group> $blockingGroups
     */
    private function flashOwnerDeletionBlocked(User $user, array $blockingGroups): void
    {
        $itemsHtml = [];
        foreach ($blockingGroups as $group) {
            $successor = $this->groupOwnerTransfer->findSuggestedSuccessor($group, $user);
            $editUrl = $this->adminUrlGenerator
                ->setController(GroupCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($group->getId())
                ->generateUrl();

            $hint = null !== $successor
                ? $this->t('admin.crud.user.error_delete_owner_hint_suggested', [
                    '%name%' => $successor->getAdminLabel(),
                ])
                : $this->t('admin.crud.user.error_delete_owner_hint_pick');

            $itemsHtml[] = $this->t('admin.crud.user.error_delete_owner_flash_item', [
                '%group%' => $group->getName(),
                '%hint%' => $hint,
                '%url%' => $editUrl,
                '%btn%' => $this->t('admin.crud.user.error_delete_owner_flash_btn'),
            ]);
        }

        $this->addFlash('danger', $this->t('admin.crud.user.error_delete_owner_flash', [
            '%user%' => $user->getAdminLabel(),
            '%count%' => (string) count($blockingGroups),
            '%items%' => implode('', $itemsHtml),
        ]));
    }

    private function redirectToUserIndex(): Response
    {
        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->unset(EA::ENTITY_ID)
                ->generateUrl(),
        );
    }

    private function assertCanDeleteTarget(User $target): void
    {
        if (!$this->canDeleteTarget($target)) {
            throw new AccessDeniedException($this->t('admin.crud.user.error_admin_target'));
        }
    }

    private function getPlatformBanReason(): string
    {
        $context = $this->getContext();
        if (null === $context) {
            return '';
        }

        $formName = basename(str_replace('\\', '/', self::getEntityFqcn()));
        $payload = $context->getRequest()->request->all()[$formName] ?? [];

        return trim((string) ($payload['platformBanReason'] ?? ''));
    }

    private function handleBanStatusChange(User $user, bool $wasBanned, bool $willBeBanned, string $reason): void
    {
        if ($wasBanned === $willBeBanned) {
            return;
        }

        try {
            if ($willBeBanned) {
                $this->assertCanBanTarget($user);
                $this->platformBanService->ban($user, $this->requireAdminUser(), $reason);

                return;
            }

            $this->platformBanService->unban($user, $this->requireAdminUser());
        } catch (\DomainException $exception) {
            throw $this->translateDomainException($exception);
        }
    }

    private function createPlatformBanReasonConstraint(): Callback
    {
        return new Callback(function (?string $value, ExecutionContextInterface $context): void {
            $form = $context->getRoot();
            if (!$form->has('isBanned') || !(bool) $form->get('isBanned')->getData()) {
                return;
            }

            $user = $form->getData();
            if (!$user instanceof User || $this->wasUserBannedBeforeSubmit($user)) {
                return;
            }

            if ('' === trim((string) $value)) {
                $context->buildViolation($this->t('admin.crud.user.error_ban_reason_required'))
                    ->addViolation();
            }
        });
    }

    private function wasUserBannedBeforeSubmit(User $user): bool
    {
        if (null === $user->getId()) {
            return false;
        }

        $unitOfWork = $this->entityManager->getUnitOfWork();
        if (!$unitOfWork->isInIdentityMap($user)) {
            return $user->isBanned();
        }

        $original = $unitOfWork->getOriginalEntityData($user);

        return (bool) ($original['isBanned'] ?? false);
    }

    private function requireAdminUser(): User
    {
        $admin = $this->getUser();
        if (!$admin instanceof User) {
            throw new AccessDeniedException($this->t('admin.access.session_changed'));
        }

        return $admin;
    }

    private function assertCanBanTarget(User $target): void
    {
        if (!\in_array(User::ROLE_ADMIN, $target->getRoles(), true)) {
            return;
        }

        if (!$this->isGranted(User::ROLE_ADMIN)) {
            throw new AccessDeniedException($this->t('admin.crud.user.error_admin_target'));
        }
    }

    private function canDeleteTarget(User $target): bool
    {
        if (\in_array(User::ROLE_ADMIN, $target->getRoles(), true)) {
            return $this->isGranted(User::ROLE_ADMIN);
        }

        return $this->isGranted(User::ROLE_SUPER_MODERATOR);
    }
}
