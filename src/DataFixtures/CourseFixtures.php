<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\CourseVideo;
use App\Enum\Currency;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Cours 1 : Anglais des affaires
        $course1 = new Course();
        $course1->setTitle('Anglais des affaires');
        $course1->setTitleEn('Business English');
        $course1->setDescription('Maîtrisez l\'anglais professionnel pour réussir en affaires');
        $course1->setDescriptionEn('Master professional English to succeed in business');
        $course1->setPrice('49.99');
        $course1->setCurrency(Currency::USD);
        $course1->setIsPublished(true);
        $course1->setPosition(1);
        $manager->persist($course1);

        // Ajouter des vidéos au cours 1
        for ($i = 1; $i <= 3; $i++) {
            $video = new CourseVideo();
            $video->setTitle("Leçon $i - Anglais des affaires");
            $video->setTitleEn("Lesson $i - Business English");
            $video->setDescription("Contenu de la leçon $i");
            $video->setVideoFile("/uploads/videos/business-english-$i.mp4");
            $video->setDuration(1200 + ($i * 300)); // 20 min + variations
            $video->setPosition($i);
            $video->setIsFreePreview($i === 1); // Première vidéo gratuite
            $video->setCourse($course1);
            $manager->persist($video);
        }

        // Cours 2 : Français conversationnel
        $course2 = new Course();
        $course2->setTitle('Français conversationnel');
        $course2->setTitleEn('Conversational French');
        $course2->setDescription('Apprenez à converser naturellement en français');
        $course2->setDescriptionEn('Learn to speak French naturally');
        $course2->setPrice('39.99');
        $course2->setCurrency(Currency::USD);
        $course2->setIsPublished(true);
        $course2->setPosition(2);
        $manager->persist($course2);

        // Ajouter des vidéos au cours 2
        for ($i = 1; $i <= 4; $i++) {
            $video = new CourseVideo();
            $video->setTitle("Module $i - Français conversationnel");
            $video->setTitleEn("Module $i - Conversational French");
            $video->setDescription("Contenu du module $i");
            $video->setVideoFile("/uploads/videos/french-conversation-$i.mp4");
            $video->setDuration(1500 + ($i * 200));
            $video->setPosition($i);
            $video->setIsFreePreview($i === 1);
            $video->setCourse($course2);
            $manager->persist($video);
        }

        // Cours 3 : Espagnol pour débutants
        $course3 = new Course();
        $course3->setTitle('Espagnol pour débutants');
        $course3->setTitleEn('Spanish for Beginners');
        $course3->setDescription('Commencez votre voyage en espagnol');
        $course3->setDescriptionEn('Start your Spanish journey');
        $course3->setPrice('29.99');
        $course3->setCurrency(Currency::USD);
        $course3->setIsPublished(true);
        $course3->setPosition(3);
        $manager->persist($course3);

        // Ajouter des vidéos au cours 3
        for ($i = 1; $i <= 5; $i++) {
            $video = new CourseVideo();
            $video->setTitle("Leçon $i - Espagnol débutant");
            $video->setTitleEn("Lesson $i - Spanish Beginner");
            $video->setDescription("Contenu de la leçon $i");
            $video->setVideoFile("/uploads/videos/spanish-beginner-$i.mp4");
            $video->setDuration(900 + ($i * 150));
            $video->setPosition($i);
            $video->setIsFreePreview($i === 1);
            $video->setCourse($course3);
            $manager->persist($video);
        }

        $manager->flush();
    }
}

