<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\JoinsRepository")
 */
class Joins
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $list_id;

    /**
     * @ORM\Column(type="integer")
     */
    private $movie_id;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListId(): ?int
    {
        return $this->list_id;
    }

    public function setListId(int $list_id): self
    {
        $this->list_id = $list_id;

        return $this;
    }

    public function getMovieId(): ?int
    {
        return $this->movie_id;
    }

    public function setMovieId(int $movie_id): self
    {
        $this->movie_id = $movie_id;

        return $this;
    }
}
