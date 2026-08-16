<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Payment\PaymentCrudController;
use App\Controller\Admin\Teacher\DeactivateCrudController;
use App\Controller\Admin\Teacher\OnWaitingTeacherCrudController;
use App\Controller\Admin\Teacher\TeacherCrudController;
use App\Entity\Category;
use App\Entity\Certification;
use App\Entity\Contact;
use App\Entity\Course;
use App\Entity\Currency;
use App\Entity\Fee;
use App\Entity\InboxThread;
use App\Entity\Language;
use App\Entity\Order;
use App\Entity\Package;
use App\Entity\Partner;
use App\Entity\Planning;
use App\Entity\Rate;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\UserCourse;
use App\Entity\UserTeacher;
use App\Service\DashboardStatsService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UploaderHelper        $uploaderHelper,
        private readonly AdminUrlGenerator     $adminUrlGenerator,
        private readonly DashboardStatsService $statsService,
        private readonly LoggerInterface       $logger,
        private readonly string                $appName,
        private readonly string                $logoPath,
        private readonly string                $faviconPath,
        private readonly string                $teacherLabel = 'Idiomaster'
    )
    {
    }

    private function teacherLabelLower(): string
    {
        return lcfirst($this->teacherLabel);
    }

    #[Route('/admin', name: 'admin')]
    public function admin(ChartBuilderInterface $chartBuilder): Response
    {
        try {
            $chart = $this->createDashboardChart($chartBuilder);
            $stats = $this->statsService->getDashboardStats();

            return $this->render('admin/index.html.twig', [
                'chart' => $chart,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du chargement du tableau de bord', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('danger', 'Une erreur est survenue lors du chargement du tableau de bord.');

            return $this->render('admin/index.html.twig', [
                'chart' => $chartBuilder->createChart(Chart::TYPE_LINE),
                'stats' => null
            ]);
        }
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle(sprintf('<img src="%s" alt="%s Admin" width="130" class="admin-logo">', $this->logoPath, $this->appName))
            ->setFaviconPath($this->faviconPath)
            ->renderContentMaximized()
            ->renderSidebarMinimized()
            ->setLocales([
                'fr' => 'Français',
                'en' => 'English'
            ]);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('build/admin.css')
            ->addJsFile('build/admin.js');
    }

    public function configureMenuItems(): iterable
    {
        // Tableau de bord principal
        yield MenuItem::linkToDashboard('Tableau de bord', 'fas fa-tachometer-alt')
            ->setCssClass('dashboard-main-link');

        // Section Configuration
        yield MenuItem::section('Configuration')
            ->setCssClass('menu-section-config');

        yield MenuItem::linkToCrud('Langues', 'fas fa-language', Language::class);
        yield MenuItem::linkToCrud('Catégories', 'fas fa-tags', Category::class);
        yield MenuItem::linkToCrud('Partenaires', 'fas fa-handshake', Partner::class);

        // Section Pédagogie
        yield MenuItem::section('Pédagogie')
            ->setCssClass('menu-section-education');

        yield MenuItem::linkToCrud('Cours', 'fas fa-book-open', Course::class);
        yield MenuItem::linkToCrud('Cours étudiants', 'fas fa-user-graduate', UserCourse::class);
        yield MenuItem::linkToCrud('Relations prof-étudiant', 'fas fa-handshake', UserTeacher::class);

        // Section idiomasters (label dynamique)
        yield MenuItem::section("Gestion {$this->teacherLabel}s")
            ->setCssClass('menu-section-teachers');

        yield from $this->getTeacherMenuItems();
        yield from $this->getPlanningMenuItems();
        yield from $this->getCertificationMenuItems();

        // Section Communication
        yield MenuItem::section('Communication')
            ->setCssClass('menu-section-communication');

        yield from $this->getMessagingMenuItems();

        // Section Utilisateurs
        yield MenuItem::section('Utilisateurs')
            ->setCssClass('menu-section-users');

        yield from $this->getUserMenuItems();
        yield MenuItem::linkToCrud('Contacts', 'fas fa-address-book', Contact::class);

        // Section Comptabilité
        yield MenuItem::section('Comptabilité')
            ->setCssClass('menu-section-accounting');

        yield from $this->getPaymentMenuItems();
        yield from $this->getAccountingMenuItems();

        // Section Administration
        yield MenuItem::section('Administration')
            ->setCssClass('menu-section-admin');

        yield MenuItem::linkToUrl('Statistiques avancées', 'fas fa-chart-bar', '/admin/stats')
            ->setLinkTarget('_blank');

        yield MenuItem::linkToUrl('Logs système', 'fas fa-file-alt', '/admin/logs')
            ->setLinkTarget('_blank');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        /** @var User $user */
        $avatarUrl = $user->getThumbnail()
            ? $this->uploaderHelper->asset($user->getThumbnail(), 'file')
            : '/images/default-avatar.png';

        return parent::configureUserMenu($user)
            ->setName($user->getFullname())
            ->addMenuItems([
                MenuItem::linkToUrl('Mon profil', 'fas fa-user', '/admin/profile'),
                MenuItem::section(),
                MenuItem::linkToUrl('Paramètres', 'fas fa-cog', '/admin/settings'),
                MenuItem::linkToUrl('Sécurité', 'fas fa-shield-alt', '/admin/security'),
            ]);
    }

    private function createDashboardChart(ChartBuilderInterface $chartBuilder): Chart
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);

        // Configuration du graphique
        $chart->setData([
            'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
            'datasets' => [
                [
                    'label' => 'Nouveaux utilisateurs',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                    'data' => $this->statsService->getMonthlyUserStats(),
                ],
                [
                    'label' => 'Nouveaux cours',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                    'borderColor' => 'rgba(255, 99, 132, 1)',
                    'data' => $this->statsService->getMonthlyCourseStats(),
                ]
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => 'Évolution mensuelle'
                ]
            ]
        ]);

        return $chart;
    }

    private function getTeacherMenuItems(): iterable
    {
        yield MenuItem::subMenu("{$this->teacherLabel}s", 'fas fa-chalkboard-teacher')
            ->setCssClass('submenu-teachers')
            ->setSubItems([
                MenuItem::linkToUrl("Tous les {$this->teacherLabelLower()}s", 'fas fa-list',
                    $this->adminUrlGenerator
                        ->setController(TeacherCrudController::class)
                        ->setAction(Crud::PAGE_INDEX)
                ),

                MenuItem::linkToUrl('En attente de validation', 'fas fa-clock',
                    $this->adminUrlGenerator
                        ->setController(OnWaitingTeacherCrudController::class)
                        ->setAction(Crud::PAGE_INDEX)
                ),

                MenuItem::linkToUrl("{$this->teacherLabel}s désactivés", 'fas fa-user-slash',
                    $this->adminUrlGenerator
                        ->setController(DeactivateCrudController::class)
                        ->setAction(Crud::PAGE_INDEX)
                ),

                MenuItem::section('Actions'),

                MenuItem::linkToUrl("Ajouter un {$this->teacherLabelLower()}", 'fas fa-plus-circle',
                    $this->adminUrlGenerator
                        ->setController(TeacherCrudController::class)
                        ->setAction(Crud::PAGE_NEW)
                )->setCssClass('menu-action-add'),
            ]);
    }

    private function getPlanningMenuItems(): iterable
    {
        yield MenuItem::subMenu('Plannings', 'fas fa-calendar-alt')
            ->setCssClass('submenu-planning')
            ->setSubItems([
                MenuItem::linkToCrud('Tous les plannings', 'fas fa-list', Planning::class),

                MenuItem::linkToCrud('Planning du jour', 'fas fa-calendar-day', Planning::class)
                    ->setQueryParameter('filter', 'today'),

                MenuItem::linkToCrud('Planning de la semaine', 'fas fa-calendar-week', Planning::class)
                    ->setQueryParameter('filter', 'week'),

                MenuItem::section('Actions'),

                MenuItem::linkToCrud('Nouveau planning', 'fas fa-plus-circle', Planning::class)
                    ->setAction(Action::NEW)
                    ->setCssClass('menu-action-add'),
            ]);
    }

    private function getCertificationMenuItems(): iterable
    {
        yield MenuItem::subMenu('Certifications', 'fas fa-certificate')
            ->setCssClass('submenu-certifications')
            ->setSubItems([
                MenuItem::linkToCrud('Tous les certificats', 'fas fa-award', Certification::class),

                MenuItem::linkToCrud('Certificats validés', 'fas fa-check-circle', Certification::class)
                    ->setQueryParameter('filter', 'validated'),

                MenuItem::linkToCrud('En attente de validation', 'fas fa-hourglass-half', Certification::class)
                    ->setQueryParameter('filter', 'pending'),

                MenuItem::section('Actions'),

                MenuItem::linkToCrud('Nouveau certificat', 'fas fa-plus-circle', Certification::class)
                    ->setAction(Action::NEW)
                    ->setCssClass('menu-action-add'),
            ]);
    }

    private function getMessagingMenuItems(): iterable
    {
        yield MenuItem::subMenu('Messagerie', 'fas fa-comments')
            ->setCssClass('submenu-messaging')
            ->setSubItems([
                MenuItem::linkToCrud('Toutes les discussions', 'fas fa-inbox', InboxThread::class),

                MenuItem::linkToCrud('Discussions actives', 'fas fa-fire', InboxThread::class)
                    ->setQueryParameter('filter', 'active'),

                MenuItem::linkToCrud('Messages non lus', 'fas fa-envelope', InboxThread::class)
                    ->setQueryParameter('filter', 'unread'),

                MenuItem::section('Actions'),

                MenuItem::linkToCrud('Nouvelle discussion', 'fas fa-plus-circle', InboxThread::class)
                    ->setAction(Action::NEW)
                    ->setCssClass('menu-action-add'),
            ]);
    }

    private function getUserMenuItems(): iterable
    {
        yield MenuItem::subMenu('Utilisateurs', 'fas fa-users')
            ->setCssClass('submenu-users')
            ->setSubItems([
                MenuItem::linkToCrud('Tous les utilisateurs', 'fas fa-list', User::class),

                MenuItem::linkToCrud('Utilisateurs actifs', 'fas fa-user-check', User::class)
                    ->setQueryParameter('filter', 'active'),

                MenuItem::linkToCrud('Étudiants', 'fas fa-user-graduate', User::class)
                    ->setQueryParameter('filter', 'students'),

                MenuItem::linkToCrud("{$this->teacherLabel}s", 'fas fa-chalkboard-teacher', User::class)
                    ->setQueryParameter('filter', 'teachers'),

                MenuItem::section('Actions'),

                MenuItem::linkToCrud('Nouvel utilisateur', 'fas fa-user-plus', User::class)
                    ->setAction(Action::NEW)
                    ->setCssClass('menu-action-add'),
            ]);
    }

    private function getPaymentMenuItems(): iterable
    {
        yield MenuItem::subMenu('Paiements', 'fas fa-credit-card')
            ->setCssClass('submenu-payments')
            ->setSubItems([
                MenuItem::linkToUrl('Tous les paiements', 'fas fa-list',
                    $this->adminUrlGenerator
                        ->setController(PaymentCrudController::class)
                        ->setAction(Crud::PAGE_INDEX)
                ),

                MenuItem::linkToUrl('Paiements réussis', 'fas fa-check-circle',
                    $this->adminUrlGenerator
                        ->setController(PaymentCrudController::class)
                        ->setAction(Crud::PAGE_INDEX)
                        ->set('filter', 'success')
                ),

                MenuItem::linkToUrl('En attente', 'fas fa-hourglass-half',
                    $this->adminUrlGenerator
                        ->setController(PaymentCrudController::class)
                        ->setAction(Crud::PAGE_INDEX)
                        ->set('filter', 'pending')
                ),

                MenuItem::linkToUrl('Paiements échoués', 'fas fa-times-circle',
                    $this->adminUrlGenerator
                        ->setController(PaymentCrudController::class)
                        ->setAction(Crud::PAGE_INDEX)
                        ->set('filter', 'failed')
                ),

                MenuItem::section('Actions'),

                MenuItem::linkToUrl('Nouveau paiement', 'fas fa-plus-circle',
                    $this->adminUrlGenerator
                        ->setController(PaymentCrudController::class)
                        ->setAction(Crud::PAGE_NEW)
                )->setCssClass('menu-action-add'),
            ]);
    }

    private function getAccountingMenuItems(): iterable
    {
        yield MenuItem::subMenu('Comptabilité avancée', 'fas fa-chart-pie')
            ->setCssClass('submenu-accounting')
            ->setSubItems([
                MenuItem::linkToCrud('Commandes', 'fas fa-shopping-cart', Order::class),
                MenuItem::linkToCrud('Transactions', 'fas fa-exchange-alt', Transaction::class),
                MenuItem::linkToCrud('Commissions', 'fas fa-percentage', Fee::class),

                MenuItem::section('Configuration'),

                MenuItem::linkToCrud('Devises', 'fas fa-coins', Currency::class),
                MenuItem::linkToCrud('Taux de change', 'fas fa-chart-line', Rate::class),
                MenuItem::linkToCrud('Packages', 'fas fa-box', Package::class),
            ]);
    }
}
