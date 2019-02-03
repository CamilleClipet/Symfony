<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ListsRepository;



class UserController extends AbstractController
{

/**
*@Route("/profile/delete", name="user_delete")
*/
public function deleteUser(ObjectManager $manager, ListsRepository $repo)
{

  $user = $this->container->get('security.token_storage')->getToken()->getUser();
  $lists = $repo->findBy(['user_id'=> $user->getId()]);

  $manager->remove($user);
  $manager->remove($lists);
  $manager->flush();

  return $this->redirectToRoute('home');
}

}


 ?>
