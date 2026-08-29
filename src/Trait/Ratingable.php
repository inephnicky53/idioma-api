<?php

namespace App\Trait;

use App\Entity\Rating;
use App\Exception\RatingValueException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

trait Ratingable
{
    private Collection $ratings;

    public function __construct()
    {
        $this->ratings = new ArrayCollection();
    }

    /**
     * @return Collection<int, Rating>
     */
    #[Groups(['course:view', 'teacher:show'])]
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    #[Groups(['teacher:list', 'teacher:show', 'course:list'])]
    public function getRatingStars(): array
    {
        $model = [
            'rate' => 0,
            'count' => 0,
        ];

        $stars = [
            1 => $model,
            1.5 => $model,
            2 => $model,
            2.5 => $model,
            3 => $model,
            3.5 => $model,
            4 => $model,
            4.5 => $model,
            5 => $model,
        ];

        $ratings = $this->ratings;
        if ($ratings->count() == 0) return $stars;

        $ratings->map(function (Rating $rating) use (&$stars) {
            switch ($rating->getStars()) {
                case 5:
                    $stars[5]['count']++;
                    break;
                case 4.5:
                    $stars[4.5]['count']++;
                    break;
                case 4:
                    $stars[4]['count']++;
                    break;
                case 3.5:
                    $stars[3.5]['count']++;
                    break;
                case 3:
                    $stars[3]['count']++;
                    break;
                case 2.5:
                    $stars[2.5]['count']++;
                    break;
                case 2:
                    $stars[2]['count']++;
                    break;
                case 1.5:
                    $stars[1.5]['count']++;
                    break;
                case 1:
                    $stars[1]['count']++;
                    break;
                default:
                    throw new RatingValueException();
            }
        });

        $starsMoy = [];
        foreach ($stars as $index => $star) {
            $starsMoy[$index] = [
                'rate' => ($star['count'] * 100) / $ratings->count(),
                'count' => $star['count']
            ];
        }
        return $starsMoy;
    }

    #[Groups(['teacher:list', 'teacher:show', 'course:list'])]
    public function getRatingsCount(): int
    {
        return $this->ratings->count();
    }

    public function addRating(Rating $rating): static
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings->add($rating);
            $rating->setTeacher($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): static
    {
        if ($this->ratings->removeElement($rating)) {
            if ($rating->getTeacher() === $this) {
                $rating->setTeacher(null);
            }
        }

        return $this;
    }
}
