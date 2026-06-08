<?php

declare(strict_types=1);

namespace App\Controller\Admin\Crud;

use App\Admin\EasyAdmin\MessageThreadContextFormatter;
use App\Entity\Message;
use App\Entity\User;
use App\Enum\PlatformNoticeVariant;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

final class MessageCrudController extends AbstractAdminCrudController
{
    public function __construct(
        private readonly MessageThreadContextFormatter $threadContextFormatter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Message::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->setIndexSectionTitle($crud, 'admin.menu.messages')
            ->setEntityLabelInSingular($this->t('admin.crud.message.entity_singular'))
            ->setEntityLabelInPlural($this->t('admin.crud.message.entity_plural'))
            ->setEntityPermission(User::ROLE_MODERATOR)
            ->setSearchFields(['id'])
            ->setPaginatorPageSize(25)
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPageTitle(Crud::PAGE_DETAIL, fn (Message $message): string => $this->t('admin.crud.message.page_detail', [
                '%id%' => (string) ($message->getId() ?? $this->t('admin.crud.common.unknown_id')),
                '%channel%' => $this->t($message->getChannelLabelKey()),
            ]));
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
            ->add(BooleanFilter::new('isStaffAnnouncement', $this->t('admin.crud.message.filter_staff_announcement')))
            ->add(BooleanFilter::new('isPlatformNotice', $this->t('admin.crud.message.filter_platform_notice')))
            ->add($this->entityFilter('relatedGroup', $this->t('admin.crud.message.field_group')))
            ->add($this->entityFilter('author', $this->t('admin.crud.message.field_author')));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');

        yield FormField::addFieldset($this->t('admin.crud.message.fieldset_content'));
        yield TextareaField::new('content', $this->t('admin.crud.message.field_content_full'))
            ->hideOnIndex()
            ->setFormTypeOption('attr', ['rows' => 8, 'readonly' => true]);

        yield TextField::new('content', $this->t('admin.crud.message.field_content_excerpt'))
            ->onlyOnIndex()
            ->formatValue(static fn (?string $value): string => null !== $value && mb_strlen($value) > 80
                ? mb_substr($value, 0, 80).'…'
                : (string) $value);

        yield FormField::addFieldset($this->t('admin.crud.message.fieldset_thread'))
            ->onlyOnDetail();
        yield TextareaField::new('threadContext', $this->t('admin.crud.message.field_thread_context'))
            ->onlyOnDetail()
            ->setFormTypeOption('attr', ['rows' => 16, 'readonly' => true])
            ->formatValue(fn (?string $value, Message $entity): string => $this->threadContextFormatter->format($entity));

        yield IdField::new('id')->onlyOnDetail();
        yield $this->entityAssociation('author', $this->t('admin.crud.message.field_author'));
        yield $this->entityAssociation('recipient', $this->t('admin.crud.message.field_recipient'));
        yield $this->entityAssociation('relatedGroup', $this->t('admin.crud.message.field_group'));
        yield $this->entityAssociation('parent', $this->t('admin.crud.message.field_parent'))->hideOnIndex();

        yield FormField::addFieldset($this->t('admin.crud.message.fieldset_metadata'));
        yield BooleanField::new('isStaffAnnouncement', $this->t('admin.crud.message.field_staff_announcement'));
        yield BooleanField::new('isPlatformNotice', $this->t('admin.crud.message.field_platform_notice'));
        yield ChoiceField::new('platformNoticeVariant', $this->t('admin.crud.message.field_notice_variant'))
            ->setChoices($this->noticeVariantChoices())
            ->hideOnIndex();
        yield $this->adminDateTimeField('createdAt', $this->t('admin.crud.common.created_at'), $pageName);
        yield $this->adminDateTimeField('authorHiddenAt', $this->t('admin.crud.message.field_hidden_by_author'), $pageName)->hideOnIndex();
        yield $this->adminDateTimeField('recipientHiddenAt', $this->t('admin.crud.message.field_hidden_by_recipient'), $pageName)->hideOnIndex();
        yield $this->adminDateTimeField('repliesClosedAt', $this->t('admin.crud.message.field_replies_closed'), $pageName)->hideOnIndex();
    }

    /**
     * @return array<string, PlatformNoticeVariant|null>
     */
    private function noticeVariantChoices(): array
    {
        $choices = [$this->t('admin.crud.common.none') => null];
        foreach (PlatformNoticeVariant::cases() as $variant) {
            $choices[$this->t('admin.crud.message.notice_variant.'.$variant->value)] = $variant;
        }

        return $choices;
    }
}
