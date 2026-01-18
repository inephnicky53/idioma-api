<?php

namespace App\Controller\Admin;

use App\Entity\CheckIn;
use App\Entity\ContactMessage;
use App\Entity\Course;
use App\Entity\CourseVideo;
use App\Entity\Payment;
use App\Entity\Rate;
use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\TimeSlot;
use App\Entity\User;
use App\Service\DashboardStatsService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private DashboardStatsService $statsService,
        private RequestStack $requestStack
    ) {}

    public function index(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $period = $request ? $request->query->get('period', 'today') : 'today';
        $stats = $this->statsService->getStats($period);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'period' => $period,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Idioma Club Admin')
            ->setFaviconPath('favicon.ico')
            ->setTranslationDomain('admin');
    }



    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de Bord', 'fa fa-home');

        yield MenuItem::section('Gestion des Utilisateurs');
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-users', User::class);

        yield MenuItem::section('Abonnements');
        yield MenuItem::linkToCrud('Plans d\'abonnement', 'fas fa-list', SubscriptionPlan::class);
        yield MenuItem::linkToCrud('Abonnements', 'fas fa-ticket', Subscription::class);
        yield MenuItem::linkToCrud('Paiements', 'fas fa-credit-card', Payment::class);

        yield MenuItem::section('Cours');
        yield MenuItem::linkToCrud('Cours', 'fas fa-graduation-cap', Course::class);
        yield MenuItem::linkToCrud('Vidéos', 'fas fa-video', CourseVideo::class);

        yield MenuItem::section('Présences');
        yield MenuItem::linkToCrud('Check-ins', 'fas fa-sign-in-alt', CheckIn::class);

        yield MenuItem::section('Contact');
        yield MenuItem::linkToCrud('Messages de Contact', 'fas fa-envelope', ContactMessage::class);

        yield MenuItem::section('Configuration');
        yield MenuItem::linkToCrud('Taux de change', 'fas fa-exchange-alt', Rate::class);

        yield MenuItem::section('Héritage');
        yield MenuItem::linkToCrud('Membres Actifs (Ce Mois)', 'fas fa-user-check', User::class)
            ->setController(ActiveSubscriptionUserCrudController::class);
        yield MenuItem::linkToCrud('Créneaux Horaires', 'fas fa-clock', TimeSlot::class);

        yield MenuItem::section('API');
        yield MenuItem::linkToUrl('Documentation API', 'fas fa-book', '/api/docs');
    }
}
