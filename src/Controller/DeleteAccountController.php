<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountDeletionGroupSuccessionFormType;
use App\Form\DeleteAccountFormType;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\AccountDeletionService;
use App\Service\GroupOwnerTransferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profil/suppression', name: 'app_profile_delete_account')]
#[IsGranted('ROLE_USER')]
final class DeleteAccountController extends AbstractAppController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        AccountDeletionService $accountDeletion,
        EntityManagerInterface $entityManager,
        Security $security,
        GroupRepository $groupRepository,
        GroupMemberRepository $groupMemberRepository,
        GroupOwnerTransferService $groupOwnerTransfer,
        UserRepository $userRepository,
        #[Autowire(service: 'limiter.account_deletion_request')]
        RateLimiterFactory $accountDeletionRequestLimiter,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($accountDeletion->ownsGroups($user)) {
            return $this->handleGroupSuccession(
                $request,
                $user,
                $groupRepository,
                $groupMemberRepository,
                $groupOwnerTransfer,
                $userRepository,
            );
        }

        $form = $this->createForm(DeleteAccountFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $accountDeletionRequestLimiter->create($request->getClientIp() ?? 'unknown');
            $rateLimit = $limiter->consume(1);

            if (!$rateLimit->isAccepted()) {
                $this->addWarningFlash($this->trans('profile.account_deletion_rate_limited'));

                return $this->redirectToRoute('app_profile_delete_account');
            }

            try {
                $accountDeletion->requestAccountDeletion($user);
                $entityManager->flush();
            } catch (TransportExceptionInterface) {
                $this->addErrorFlash($this->trans('profile.account_deletion_email_failed'));

                return $this->render('profile/delete_account.html.twig', [
                    'deleteAccountForm' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $this->addSuccessFlash($this->trans('profile.account_deletion_email_sent'));

            if (null !== $security->getUser()) {
                $security->logout(false);
            }

            return $this->redirectToRoute('app_login');
        }

        $response = null;
        if ($form->isSubmitted() && !$form->isValid()) {
            $response = new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('profile/delete_account.html.twig', [
            'deleteAccountForm' => $form,
        ], $response);
    }

    /**
     * @return list<array{group: \App\Entity\Group, successors: list<User>, hasOtherMembers: bool}>
     */
    private function buildGroupSuccessionConfigs(
        User $user,
        GroupRepository $groupRepository,
        GroupMemberRepository $groupMemberRepository,
        GroupOwnerTransferService $groupOwnerTransfer,
    ): array {
        $configs = [];
        foreach ($groupRepository->findOwnedByUser($user) as $group) {
            $hasOtherMembers = $groupOwnerTransfer->hasOtherMembers($group, $user);
            $successors = [];
            if ($hasOtherMembers) {
                foreach ($groupMemberRepository->findOtherMembersOrdered($group, $user) as $member) {
                    $successors[] = $member->getUser();
                }
            }

            $configs[] = [
                'group' => $group,
                'successors' => $successors,
                'hasOtherMembers' => $hasOtherMembers,
            ];
        }

        return $configs;
    }

    private function handleGroupSuccession(
        Request $request,
        User $user,
        GroupRepository $groupRepository,
        GroupMemberRepository $groupMemberRepository,
        GroupOwnerTransferService $groupOwnerTransfer,
        UserRepository $userRepository,
    ): Response {
        $groupConfigs = $this->buildGroupSuccessionConfigs(
            $user,
            $groupRepository,
            $groupMemberRepository,
            $groupOwnerTransfer,
        );

        $form = $this->createForm(AccountDeletionGroupSuccessionFormType::class, null, [
            'groups' => $groupConfigs,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                foreach ($groupConfigs as $config) {
                    $group = $config['group'];
                    $groupId = (string) $group->getId();

                    if ($config['hasOtherMembers']) {
                        $successorId = (int) $form->get('successor_'.$groupId)->getData();
                        $successor = $userRepository->findActiveById($successorId);
                        if (null === $successor) {
                            throw new \DomainException('flash.group.transfer_target_deleted');
                        }

                        $becomeModerator = (bool) $form->get('become_moderator_'.$groupId)->getData();
                        $groupOwnerTransfer->transferOwnershipByCurrentOwner(
                            $user,
                            $group,
                            $successor,
                            $becomeModerator,
                        );
                    } else {
                        $groupOwnerTransfer->dissolveGroupAsOwner($user, $group);
                    }
                }

                $this->addSuccessFlash($this->trans('ui.profile.delete_blocked.succession_done'));

                return $this->redirectToRoute('app_profile_delete_account');
            } catch (\DomainException $exception) {
                $this->addErrorFlash($this->trans($exception->getMessage()));
            }
        }

        $response = null;
        if ($form->isSubmitted() && !$form->isValid()) {
            $response = new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('profile/delete_account_blocked.html.twig', [
            'successionForm' => $form,
            'groupConfigs' => $groupConfigs,
        ], $response);
    }
}
