<?php

namespace App\Controller\Admin;

use App\Entity\News;
use App\Enum\NewsStatus;
use App\Service\NewsletterDistributionService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NewsCrudController extends AbstractCrudController
{
    public function __construct(
        private NewsletterDistributionService $newsletterDistributionService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return News::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Actualité')
            ->setEntityLabelInPlural('Actualités')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices([
                    'Brouillon' => NewsStatus::DRAFT->value,
                    'Publié' => NewsStatus::PUBLISHED->value,
                    'Archivé' => NewsStatus::ARCHIVED->value,
                ]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('title', 'Titre')
            ->setRequired(true);

        yield TextField::new('excerpt', 'Extrait')
            ->setHelp('Court résumé de l\'actualité (max 500 caractères)')
            ->hideOnIndex();

        yield TextareaField::new('content', 'Contenu')
            ->setRequired(true)
            ->hideOnIndex();

        yield ImageField::new('image', 'Image')
            ->setBasePath('/uploads/news')
            ->setUploadDir('public/uploads/news')
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setRequired(false);

        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'Brouillon' => NewsStatus::DRAFT,
                'Publié' => NewsStatus::PUBLISHED,
                'Archivé' => NewsStatus::ARCHIVED,
            ])
            ->renderAsBadges([
                NewsStatus::DRAFT->value => 'warning',
                NewsStatus::PUBLISHED->value => 'success',
                NewsStatus::ARCHIVED->value => 'secondary',
            ]);

        yield DateTimeField::new('publishedAt', 'Date de publication')
            ->hideOnIndex();

        yield BooleanField::new('isSentToNewsletter', 'Envoyé à la newsletter')
            ->renderAsSwitch(false);

        yield DateTimeField::new('sentAt', 'Envoyé le')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Créé le')
            ->onlyOnIndex();
    }

    public function sendToNewsletter(Request $request): Response
    {
        $newsId = $request->query->get('id');
        $news = $this->getRepository(News::class)->find($newsId);

        if (!$news) {
            throw $this->createNotFoundException('Actualité non trouvée');
        }

        if ($news->isSentToNewsletter()) {
            $this->addFlash('warning', 'Cette actualité a déjà été envoyée à la newsletter');
            return $this->redirectToRoute('admin', ['crudAction' => 'detail', 'crudControllerFqcn' => NewsCrudController::class, 'entityId' => $newsId]);
        }

        try {
            $results = $this->newsletterDistributionService->sendNewsToSubscribers($news);

            $message = sprintf(
                'Actualité envoyée à %d abonnés (%d erreurs)',
                $results['sent'],
                $results['failed']
            );

            $this->addFlash('success', $message);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin', ['crudAction' => 'detail', 'crudControllerFqcn' => NewsCrudController::class, 'entityId' => $newsId]);
    }
}
