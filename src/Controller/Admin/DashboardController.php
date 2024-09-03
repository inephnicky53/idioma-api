<?php

namespace App\Controller\Admin;

use App\ApiResource\StatsRessource;
use App\Controller\Admin\Teacher\DeactivateCrudController;
use App\Controller\Admin\Teacher\OnWaitingTeacherCrudController;
use App\Controller\Admin\Teacher\TeacherCrudController;
use App\Entity\Category;
use App\Entity\Certification;
use App\Entity\Course;
use App\Entity\Currency;
use App\Entity\InboxThread;
use App\Entity\Language;
use App\Entity\Order;
use App\Entity\Package;
use App\Entity\Planning;
use App\Entity\Rate;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\UserCourse;
use App\Entity\UserTeacher;
use App\Repository\PlanningRepository;
use App\Repository\UserCourseRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UploaderHelper    $uploaderHelper,
        private readonly AdminUrlGenerator $adminUrlGenerator,

    )
    {
    }

    #[Route('/admin', name: 'admin')]
    public function admin(
        ChartBuilderInterface $chartBuilder,
        UserRepository        $userRepository,
        UserCourseRepository  $userCourseRepository,
        PlanningRepository    $planningRepository
    ): Response
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $stats = new StatsRessource();

        $users = new ArrayCollection();
        $teachers = new ArrayCollection();
        $students = new ArrayCollection();

        $userCourses = $userCourseRepository->findAll();
        $plannings = $planningRepository->findAll();

        array_map(function (User $user) use (&$users, &$teachers, &$students) {
            $users->add($user);
            if (in_array($user->getRoles(), [User::STUDENT]))
                $students->add($user);
            if ($user->getTeacher())
                $teachers->add($user);
        }, $userRepository->findAll());

        array_map(function (UserCourse $userCourse) {

        }, $userCourses);

        array_map(function (Planning $planning) {

        }, $plannings);

        $stats->total['users'] = count($users);
        $stats->total['teachers'] = $teachers->count();
        $stats->total['students'] = $students->count();
        $stats->total['courses'] = count($userCourses);
        $stats->total['waiting_courses'] = 0;
        $stats->total['hours_courses'] = 0;

        return $this->render('admin/index.html.twig', [
            'chart' => $chart,
            'stats' => $stats
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/images/logo.png" alt="logo" width="125">')
            ->setFaviconPath('/images/favicon.ico');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::section('Générales');
        yield MenuItem::linkToCrud("Langues", "fas fa-language", Language::class);
        yield MenuItem::linkToCrud("Catégories de cours", "fas fa-language", Category::class);

        yield MenuItem::section('Cours');
        yield MenuItem::linkToCrud("Cours", "fas fa-book", Course::class);
        yield MenuItem::linkToCrud("Cours d'étudiants", "fas fa-graduation-cap", UserCourse::class);
        yield MenuItem::linkToCrud("Professeurs d'étudiants", "fas fa-plus", UserTeacher::class);

        yield MenuItem::section('Professeurs');
        yield MenuItem::subMenu("Professeurs", "fas fa-users")->setSubItems([
            MenuItem::linkToUrl('Liste', 'fas fa-list', $this->adminUrlGenerator
                ->setController(TeacherCrudController::class)
                ->setAction(Crud::PAGE_INDEX)),
            MenuItem::linkToUrl("En attente", 'fas fa-list', $this->adminUrlGenerator
                ->setController(OnWaitingTeacherCrudController::class)
                ->setAction(Crud::PAGE_INDEX)),
            MenuItem::linkToUrl("Désactivés", 'fas fa-list', $this->adminUrlGenerator
                ->setController(DeactivateCrudController::class)
                ->setAction(Crud::PAGE_INDEX)),
            MenuItem::linkToUrl("Ajouter un professeur", 'fas fa-plus', $this->adminUrlGenerator
                ->setController(TeacherCrudController::class)
                ->setAction(Crud::PAGE_NEW)),
        ]);
        yield MenuItem::subMenu("Plannings", "fas fa-calendar")->setSubItems([
            MenuItem::linkToCrud("Liste", "fas fa-list", Planning::class),
            MenuItem::linkToCrud("Ajouter un planning", "fas fa-plus", Planning::class)
                ->setAction(Action::NEW),
        ]);
        yield MenuItem::subMenu("Certifications", "fas fa-users")->setSubItems([
            MenuItem::linkToCrud("Certificats", "fas fa-book", Certification::class),
            MenuItem::linkToCrud("Ajouter un certificat", "fas fa-plus", Certification::class)
                ->setAction(Action::NEW),
        ]);
        yield MenuItem::subMenu("Messagerie", "fas fa-book")->setSubItems([
            MenuItem::linkToCrud("Toutes les discussions", "fas fa-list", InboxThread::class),
            MenuItem::linkToCrud("Créer une discussion", "fas fa-plus", InboxThread::class)
                ->setAction(Action::NEW),
        ]);

        yield MenuItem::section('Utilisateurs');
        yield MenuItem::subMenu("Utilisateurs", "fas fa-users")->setSubItems([
            MenuItem::linkToCrud("Liste", "fas fa-list", User::class),
            MenuItem::linkToCrud("Ajouter", "fas fa-plus", User::class)
                ->setAction(Action::NEW),
        ]);

        yield MenuItem::section('Comptabilité');
        yield MenuItem::subMenu("Comptabilité", "fas fa-book")->setSubItems([
            MenuItem::linkToCrud("Commandes", "fas fa-list", Order::class),
            MenuItem::linkToCrud("Transactions", "fas fa-list", Transaction::class),
            MenuItem::linkToCrud("Devises", "fas fa-list", Currency::class),
            MenuItem::linkToCrud("Taux", "fas fa-plus", Rate::class),
            MenuItem::linkToCrud("Packages", "fas fa-plus", Package::class)
        ]);
    }


    public function configureUserMenu(UserInterface $user): UserMenu
    {
        /** @var User $user */
        return parent::configureUserMenu($user)
            ->setName($user->getFullname())
            ->setAvatarUrl($user->getThumbnail() ? $this->uploaderHelper->asset($user->getThumbnail(), 'file') : null);
    }
}
