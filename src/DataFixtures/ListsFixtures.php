<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Lists;

class ListsFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        // $product = new Product();
        // $manager->persist($product);
        for ($i = 1; $i <= 10; $i++) {
          $list = new Lists();
          $list->setName("List $i")
                  ->setUserId("$i")
                  ->setCreatedAt(new \DateTime());
          $manager->persist($list);
        }
        $manager->flush();
    }
}
