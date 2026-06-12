<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Crud\EventCrudController;
use App\Controller\Admin\Crud\GroupCrudController;
use App\Controller\Admin\Crud\MessageCrudController;
use App\Controller\Admin\Crud\UserBanCrudController;
use App\Controller\Admin\Crud\UserCrudController;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Locale;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminDashboard(routePath: '/', routeName: 'ef_admin')]
#[IsGranted(User::ROLE_MODERATOR)]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard/index.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->translator->trans('admin.title'))
            ->renderContentMaximized()
            ->setTranslationDomain('messages')
            ->setTextDirection('ltr')
            ->setLocales([
                Locale::new('fr', $this->translator->trans('admin.locale.fr'), 'fa fa-fw fa-language'),
                Locale::new('en', $this->translator->trans('admin.locale.en'), 'fa fa-fw fa-language'),
            ]);
    }

    public function configureCrud(): Crud
    {
        $isEnglish = 'en' === $this->translator->getLocale();
        $dateFormat = $isEnglish ? 'MM/dd/yyyy' : 'dd/MM/yyyy';
        $dateTimeFormat = $isEnglish ? 'MM/dd/yyyy HH:mm' : 'dd/MM/yyyy HH:mm';

        return Crud::new()
            ->setDateFormat($dateFormat)
            ->setTimeFormat('HH:mm')
            ->setDateTimeFormat($dateTimeFormat)
            ->showEntityActionsInlined(false)
            ->setPageTitle(Crud::PAGE_NEW, $this->translator->trans('admin.page.new'))
            ->setPageTitle(Crud::PAGE_EDIT, $this->translator->trans('admin.page.edit'))
            ->setPageTitle(Crud::PAGE_DETAIL, $this->translator->trans('admin.page.detail'));
    }

    public function configureActions(): Actions
    {
        $confirmMessage = $this->translator->trans('admin.delete_confirm');

        $actions = parent::configureActions();

        foreach ([Crud::PAGE_INDEX, Crud::PAGE_DETAIL] as $page) {
            $actions = $actions->update($page, Action::DELETE, static fn (Action $action) => $action->askConfirmation($confirmMessage));
        }

        return $actions->update(Crud::PAGE_INDEX, Action::BATCH_DELETE, static fn (Action $action) => $action->askConfirmation($confirmMessage));
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard($this->translator->trans('admin.menu.dashboard'), 'fa fa-gauge-high');

        yield MenuItem::section($this->translator->trans('admin.section.content'));
        yield MenuItem::linkTo(UserCrudController::class, $this->translator->trans('admin.menu.users'), 'fa fa-users');
        yield MenuItem::linkTo(GroupCrudController::class, $this->translator->trans('admin.menu.groups'), 'fa fa-people-group');
        yield MenuItem::linkTo(EventCrudController::class, $this->translator->trans('admin.menu.events'), 'fa fa-calendar-days');
        yield MenuItem::linkTo(MessageCrudController::class, $this->translator->trans('admin.menu.messages'), 'fa fa-comments');
        yield MenuItem::linkTo(UserBanCrudController::class, $this->translator->trans('admin.menu.bans'), 'fa fa-ban');

        yield MenuItem::section($this->translator->trans('admin.section.site'));
        yield MenuItem::linkToRoute($this->translator->trans('admin.menu.back_to_site'), 'fa fa-arrow-left', 'app_home')
            ->setLinkTarget('_blank');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('styles/ef-admin.scss')
            ->addJsFile('js/ef-admin-idle.js');
    }
}
