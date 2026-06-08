<?php

declare(strict_types=1);

namespace App\Controller\Admin\Crud;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventKind;
use App\Enum\EventVisibility;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

final class EventCrudController extends AbstractAdminCrudController
{
    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->setIndexSectionTitle($crud, 'admin.menu.events')
            ->setEntityLabelInSingular($this->t('admin.crud.event.entity_singular'))
            ->setEntityLabelInPlural($this->t('admin.crud.event.entity_plural'))
            ->setEntityPermission(User::ROLE_MODERATOR)
            ->setSearchFields(['title', 'location', 'description'])
            ->setDefaultSort(['startDate' => 'DESC']);
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
            ->add(TextFilter::new('title'))
            ->add(ChoiceFilter::new('kind')->setChoices($this->kindChoices()))
            ->add(ChoiceFilter::new('visibility')->setChoices($this->visibilityChoices()));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.event.fieldset_event'));
        yield TextField::new('title', $this->t('admin.crud.event.field_title'));
        yield TextareaField::new('description', $this->t('admin.crud.event.field_description'))->hideOnIndex();
        yield ChoiceField::new('kind', $this->t('admin.crud.event.field_kind'))
            ->setChoices($this->kindChoices())
            ->renderAsBadges();
        yield TextField::new('location', $this->t('admin.crud.event.field_location'))->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.event.fieldset_dates'));
        yield $this->adminDateTimeField('startDate', $this->t('admin.crud.event.field_start'), $pageName);
        yield $this->adminDateTimeField('endDate', $this->t('admin.crud.event.field_end'), $pageName)->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.event.fieldset_visibility'));
        yield ChoiceField::new('visibility', $this->t('admin.crud.event.field_visibility'))
            ->setChoices($this->visibilityChoices());
        yield $this->entityAssociation('relatedGroup', $this->t('admin.crud.event.field_group'));
        yield $this->entityAssociation('author', $this->t('admin.crud.event.field_author'))->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.event.fieldset_media'))->onlyOnDetail();
        yield TextField::new('photoCover', $this->t('admin.crud.event.field_photo_cover'))->onlyOnDetail();
        yield TextField::new('photoDetail', $this->t('admin.crud.event.field_photo_detail'))->onlyOnDetail();

        yield $this->adminDateTimeField('createdAt', $this->t('admin.crud.common.created_at'), $pageName)->hideOnForm();
        yield $this->adminDateTimeField('updatedAt', $this->t('admin.crud.common.updated_at'), $pageName)->hideOnForm();
    }

    /**
     * @return array<string, EventKind>
     */
    private function kindChoices(): array
    {
        $choices = [];
        foreach (EventKind::cases() as $kind) {
            $choices[$this->t($kind->label())] = $kind;
        }

        return $choices;
    }

    /**
     * @return array<string, EventVisibility>
     */
    private function visibilityChoices(): array
    {
        $choices = [];
        foreach (EventVisibility::cases() as $visibility) {
            $choices[$this->t($visibility->label())] = $visibility;
        }

        return $choices;
    }
}
