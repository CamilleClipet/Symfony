<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Lists;
use App\Entity\Joins;
use App\Repository\ListsRepository;
use App\Repository\JoinsRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Dotenv\Dotenv;



class BlogController extends AbstractController
{

  public function __construct() {
    $this->genres = [
      '- Choose genre -' => 0,
      'Action' => 28,
      'Adventure' => 12,
      'Animation'=> 16,
      'Comedy' => 35,
      'Crime' => 80,
      'Documentary' => 99,
      'Drama' => 18,
      'Family' => 10751,
      'Fantasy' => 14,
      'History' => 36,
      'Horror' => 27,
      'Music' => 10402,
      'Mystery' => 9648,
      'Romance' => 10749,
      'Science Fiction' => 878,
      'TV Movie' => 10770,
      'Thriller' => 53,
      'War' => 10752,
      'Western' => 37
    ];

    $this->dotenv = new Dotenv();
    $this->dotenv->load(__DIR__.'/.env');
  }

  // user's homepage with his lists
  /**
  * @Route("/blog", name="blog")
  */
  public function index(ListsRepository $repo)
  {
    $user = $this->container->get('security.token_storage')->getToken()->getUser();
    $lists = $repo->findBy(['user_id' => $user->getId()]);

    if (count($lists) > 0) {
      return $this->render('blog/index.html.twig', [
        'name' => 'Movies Lists',
        'list' => $lists,
      ]);
    } else {
      return $this->render('blog/index.html.twig', [
        'name' => 'Movies Lists',
        'list' => '',
      ]);
    }
  }

  // homepage for authenticated and non authenticated users,
  // displays movies to discover
  /**
  *@Route("/", name="home")
  */
  public function home()
  {
    $this->dotenv->load(__DIR__.'/.env');
    $api_key = getenv('API_KEY');
    $html = '<div class="card-columns">';
    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.themoviedb.org/3/discover/movie?primary_release_year=2018&sort_by=popularity.desc&api_key=".$api_key,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "Content-Type: application/json",
        "cache-control: no-cache"
      ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      $results = json_decode($response, true);
      //echo count($results["results"]);
      foreach($results["results"] as $result) {
        $html .= '<div class="card">
        <a class="text-primary" href="/movie/'.$result['id'].'/2"><img class="card-img-top" src="https://image.tmdb.org/t/p/w1280'.$result['poster_path'].'" alt="Card image cap"></a>

        <div class="card-body">
          <h5 class="card-title">'.$result['title'].'</h5>';
          if ($result['overview']) {
            $html .=  '<p class="card-text">'.$result['overview'].'</p>';
          }
          $html .=  '
        </div>
        </div>';
      }
    }
    $html .= '</div>';

    return $this->render('blog/home.html.twig', [
      'title' => "Movies Lists",
      'movies' => $html
    ]);
  }


  // public lists
  /**
  *@Route("/public", name="public")
  */
  public function seePublic (ListsRepository $repo, UserRepository $repoUser)
  {
    $lists = $repo->findBy(['public' => 1]);
    $users = [];
    foreach ($lists as $list) {
      $user = $repoUser->findOneBy(['id' => $list->getUserId()]);
      if ($user == NULL) {
        array_push($users, 'deleted user');
      } else {
        array_push($users, $user->getUsername());
      }
    }

    if (count($lists) > 0) {
      return $this->render('blog/public.html.twig', [
        'name' => 'Movies Lists',
        'lists' => $lists,
        'users' => $users
      ]);
    } else {
      return $this->render('blog/public.html.twig', [
        'name' => 'Movies Lists',
        'lists' => '',
        'users' => ''

      ]);
    }
  }


  // create a new list
  /**
  *@Route("/blog/new", name="blog_create")
  */
  public function create(Request $request, ObjectManager $manager)
  {
    $user = $this->container->get('security.token_storage')->getToken()->getUser();
    //var_dump($user);
    $list = new Lists();
    $form = $this->createFormBuilder($list)
    ->add('Name')
    ->getForm();

    $form->handleRequest($request);

    if($form->isSubmitted() && $form->isValid()) {
      $list->setUserId($user->getId());
      $list->setCreatedAt(new \DateTime());
      $list->setPublic(0);

      $manager->persist($list);
      $manager->flush();

      return $this->redirectToRoute('blog_show', [
        'id' => $list->getId()
      ]);
    }

    return $this->render('blog/create.html.twig', [
      'formList' => $form->createView()
    ]);
  }


  // fill and edit a list
  /**
  *@Route("/blog/fill/{id}", name="blog_fill")
  */
  public function fill(Lists $list, JoinsRepository $repo, ListsRepository $repoLists, Request $request)
  {
    $api_key = getenv('API_KEY');
    $user = $this->container->get('security.token_storage')->getToken()->getUser();

    //redirect user if not owner of the list
    if ($user->getId() != $list->getUserId()) {
      return $this->redirect("/blog");
    }

    $movies = $repo->findBy(['list_id' => $list->getId()]);
    $favorite = $repoLists->findOneBy(['name' => 'Favorites', 'user_id' => $user->getId()]);
    //$fav_id = $favorite->getId();
    $fav_id = $favorite != NULL ? $favorite->getId() : '';
    $html = '';
    $htmlResults = '';
    $listID = $list->getId();

    //display movies in list
    foreach ($movies as $movie) {
      $movieId = $movie->getMovieId();
      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.themoviedb.org/3/movie/".$movieId."?api_key=".$api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "cache-control: no-cache"
        ),
      ));

      $response = curl_exec($curl);
      //var_dump($response);
      $err = curl_error($curl);

      curl_close($curl);

      if ($err) {
        echo "cURL Error #:" . $err;
      } else {
        $result = json_decode($response, true);
        $title = $result["title"];
      }

      $html .= '<p><a class="text-primary" href="/movie/'.$result['id'].'/2">'.$title.' </a><a href="/blog/remove/'.$listID.'/'.$result['id'].'"> <i class="fas fa-trash-alt fa-fw"></i> </a>';

      $presence = $repo->findBy(['list_id' => $fav_id, 'movie_id' => $result['id']]);

      // if the movie is already in the favorites display the "remove from
      // favorites" icon otherwise display the "add to favorites"
      if ($presence) {
        $html .= '<a href="/blog/nofavorites/'.$result['id'].'/'.$listID.'"> <i class="far fa-heart fa-fw"></i> </a></p>';
      } else {
        //$html .= '<a href="/blog/add/'.$fav_id.'/'.$result['id'].'"> <i class="fas fa-heart fa-fw"></i> </a></p>';
        $html .= '<a href="/blog/favorites/'.$result['id'].'"> <i class="fas fa-heart fa-fw"></i> </a></p>';
      }
    }

    // implement search
    $search = array('search' => null);
    $searchTitle = $this->createFormBuilder($search)
          ->add('Title', TextType::class, ['required' => false])
          ->add('Start_date', DateType::class)
          ->add('End_date', DateType::class)
          ->add('Genre', ChoiceType::class, [
            'choices'  => $this->genres,
          ])
          ->getForm();

    $searchTitle->handleRequest($request);

      if($searchTitle->isSubmitted() && $searchTitle->isValid()) {
        $data = $searchTitle->getData();
        $title = $data["Title"];
        $genre = $data["Genre"];
        $start = date_format($data["Start_date"], 'Y-m-d');
        $end = date_format($data["End_date"], 'Y-m-d');

        if ($title != NULL) {
          $title = strtolower(str_replace(' ', '+', $title));
          $query = "https://api.themoviedb.org/3/search/movie?sort_by=popularity.desc&api_key=".$api_key."&query=".$title;
        } else if ($start != NULL && $end != NULL && $genre == 0) {
          $query = "https://api.themoviedb.org/3/discover/movie?sort_by=popularity.desc&primary_release_date.gte=".$start."&primary_release_date.lte=".$end."&api_key=".$api_key;
        } else if ($genre != 0) {
          $query = "https://api.themoviedb.org/3/discover/movie?sort_by=popularity.desc&with_genres=".$genre."&api_key=".$api_key;
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $query,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_POSTFIELDS => "",
          CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json",
            "cache-control: no-cache"
          ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
          echo "cURL Error #:" . $err;
        } else {
          //echo $response;
          $results = json_decode($response, true);
          //echo count($results["results"]);
          $htmlResults .= "<h3>Search results</h3>";
          foreach($results["results"] as $result) {
            $htmlResults .= '<li>'.$result["title"].'<a href="/blog/add/'.$listID.'/'.$result['id'].'"> <i class="fas fa-plus fa-fw"></i></a></li>';
          }
        }
    }

    return $this->render('blog/fill.html.twig', [
      'list' => $list,
      'movies' => $html,
      'searchTitle' => $searchTitle->createView(),
      'searchResults' => $htmlResults
    ]);
  }


  // add movie to list and redirect to the edition page
  /**
  *@Route("/blog/add/{id}/{mid}", name="blog_add")
  */
  public function add($id, $mid, ObjectManager $manager, ListsRepository $repo, JoinsRepository $repoJoins)
  {
    $user = $this->container->get('security.token_storage')->getToken()->getUser();

    $list = $repo->findOneBy(['id' => $id]);
    //redirect user if not owner of the list
    if ($user->getId() != $list->getUserId()) {
      return $this->redirect("/blog");
    }

    $presence = $repoJoins->findOneBy(['list_id' => $id, 'movie_id' => $mid]);

    if ($presence == NULL) {
      $join = new Joins();
      $join->setListId($id);
      $join->setMovieId($mid);

      $manager->persist($join);
      $manager->flush();
    }

    return $this->redirectToRoute('blog_fill', [
      'id' => $id
    ]);;
  }


  // add the movie to the list and stay on the see movie page
  /**
  *@Route("/blog/addAndStay/{id}/{mid}", name="blog_add_stay")
  */
  public function addAndStay($id, $mid, ObjectManager $manager, ListsRepository $repoLists, JoinsRepository $repoJoins)
  {
    $user = $this->container->get('security.token_storage')->getToken()->getUser();
    $list = $repoLists->findOneBy(['id' => $id]);
    //redirect user if not owner of the list
    if ($user->getId() != $list->getUserId()) {
      return $this->redirect("/blog");
    }

    $presence = $repoJoins->findOneBy(['list_id' => $id, 'movie_id' => $mid]);

    if ($presence == NULL) {
      $join = new Joins();
      $join->setListId($id);
      $join->setMovieId($mid);

      $manager->persist($join);
      $manager->flush();
      $alert = "0";
    } else {
      $alert = "1";
    }
    //echo 'un: '.$alert;
    return $this->redirectToRoute('see_movie', [
      'id' => $mid,
      'alert' => $alert
    ]);;
  }


  // remove a movie from a list
  /**
  *@Route("/blog/remove/{id}/{mid}", name="blog_remove")
  */
  public function remove($id, $mid, JoinsRepository $repo, ListsRepository $repoLists, ObjectManager $manager)
  {
    $user = $this->container->get('security.token_storage')->getToken()->getUser();

    $list = $repoLists->findOneBy(['id' => $id]);
    //redirect user if not owner of the list
    if ($user->getId() != $list->getUserId()) {
      return $this->redirect("/blog");
    }

    $join = $repo->findOneBy(['list_id' => $id, 'movie_id' => $mid]);
    if (!$join) {
      return $this->redirectToRoute('blog_fill', [
        'id' => $id
      ]);
    }

    $manager->remove($join);
    $manager->flush();
    return $this->redirectToRoute('blog_fill', [
      'id' => $id
    ]);
  }


  // delete a list
  /**
  *@Route("/blog/delete/{id}", name="blog_delete")
  */
  public function deleteList($id, ListsRepository $repo, ObjectManager $manager)
  {
    $user = $this->container->get('security.token_storage')->getToken()->getUser();

    $list = $repo->findOneBy(['id' => $id]);
    //redirect user if not owner of the list
    if ($user->getId() != $list->getUserId()) {
      return $this->redirect("/blog");
    }
    if (!$list) {
      return $this->redirectToRoute('blog');
    }
    $manager->remove($list);
    $manager->flush();
    return $this->redirectToRoute('blog');
  }


  // add a movie to favorites
  /**
  *@Route("/blog/favorites/{id}", name="add_favorites")
  */
  public function addFavorites($id, ListsRepository $repo, ObjectManager $manager)
  {
    $user = $this->container->get('security.token_storage')->getToken()->getUser();
    $list = $repo->findOneBy(['name' => 'Favorites', 'user_id' => $user->getId()]);

    if (!$list) {
      $newList = new Lists();
      $newList->setUserId($user->getId());
      $newList->setName('Favorites');
      $newList->setCreatedAt(new \DateTime());
      $newList->setPublic(0);
      $manager->persist($newList);
      $manager->flush();
      $list = $repo->findOneBy(['name' => 'Favorites', 'user_id' => $user->getId()]);
    }

    $join = new Joins();
    $join->setListId($list->getId());
    $join->setMovieId($id);
    $manager->persist($join);
    $manager->flush();

    return $this->redirectToRoute('blog_show', [
      'id' => $list->getId()
    ]);
  }


  // remove a movie from the favorites
  /**
  *@Route("/blog/nofavorites/{mid}/{lid}", name="remove_favorites")
  */
  public function removeFavorites($mid, $lid, JoinsRepository $join_repo, ListsRepository $list_repo, ObjectManager $manager)
  {
    $user = $this->container->get('security.token_storage')->getToken()->getUser();
    $list = $list_repo->findOneBy(['name' => 'Favorites', 'user_id' => $user->getId()]);
    if (!$list) {
      return $this->redirectToRoute('blog_show', [
        'id' => $lid
      ]);
    } else {
      $join = $join_repo->findOneBy(['list_id' => $list->getId(), 'movie_id' => $mid]);
      if ($join) {
        $manager->remove($join);
        $manager->flush();
      }
    }
    return $this->redirectToRoute('blog_fill', [
      'id' => $lid
    ]);
  }


  // make list public
  /**
  *@Route("/blog/public/{id}", name="blog_public_status")
  */
  public function changePublicStatus($id, ListsRepository $repoLists, ObjectManager $manager)
  {
      $list = $repoLists->findOneBy(['id' => $id]);
      if ($list->getPublic() == 0) {
        $list->setPublic(1);
      } else if ($list->getPublic() == 1) {
        $list->setPublic(0);
      }
      $manager->persist($list);
      $manager->flush();

      return $this->redirectToRoute('blog_show', [
        'id' => $id
      ]);
  }


  // see list overview
  /**
  *@Route("/blog/{id}", name="blog_show")
  */
  public function show(Lists $list, JoinsRepository $repo, ListsRepository $repoLists, Request $request, ObjectManager $manager)
  {
    $api_key = getenv('API_KEY');
    $user = $this->container->get('security.token_storage')->getToken()->getUser();

    //redirect user if not owner of the list
    if ($user->getId() != $list->getUserId() && $list->getPublic() == 0) {
      return $this->redirect("/blog");
    }
    $is_current_user = ($user->getId() == $list->getUserId());
    $all_lists = $repoLists->findBy(['user_id' => $user->getId()]);
    $list_names = [];
    foreach($all_lists as $one_list) {
      array_push($list_names, $one_list->getName());
    }
    $movies = $repo->findBy(['list_id' => $list->getId()]);
    $favorite = $repoLists->findOneBy(['name' => 'Favorites', 'user_id' => $user->getId()]);
    $public= $repoLists->findOneBy(['public' => 1, 'user_id' => $user->getId()]);
    $list_id = $list->getId();

    if ($favorite)  {
      $fav_id = $favorite->getId();
      $fav = ($fav_id == $list_id);
    } else {
      $fav = false;
    }

    if ($public) {
      $status= "Make private";
    } else {
      $status = "Make public";
    }

    $data = [];
    $alert = '';

    foreach ($movies as $movie) {
      $movieId = $movie->getMovieId();
      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.themoviedb.org/3/movie/".$movieId."?api_key=".$api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
          "Content-Type: application/json",
          "cache-control: no-cache"
        ),
      ));

      $response = curl_exec($curl);
      $err = curl_error($curl);

      curl_close($curl);

      if ($err) {
        echo "cURL Error #:" . $err;
      } else {
        $result = json_decode($response, true);
        array_push($data,$result);
      }
    }

    if($request->request->count() > 0) {
      if (in_array($request->request->get('name'), $list_names)) {
        $alert = "This name is already taken";
      } else {
        $list->setName($request->request->get('name'));
        $manager->persist($list);
        $manager->flush();
      }
    }

    return $this->render('blog/show.html.twig', [
      'list' => $list,
      'movies' => $data,
      'is_fav' => $fav,
      'alert' => $alert,
      'status' => $status,
      'is_current_user' => $is_current_user
    ]);
  }


  // see movie page
  /**
  *@Route("/movie/{id}/{alert}", name="see_movie")
  */
  public function showMovie($id, $alert, ListsRepository $repo, JoinsRepository $repoJoins, Request $request, ObjectManager $manager)
  {
    $api_key = getenv('API_KEY');
    //echo 'deux: '.$alert;
    $user = $this->container->get('security.token_storage')->getToken()->getUser();
    $arr_result = [];
    $genres = '';
    $my_lists = [];
    $has_lists = false;

    // if user is authenticated
    if ($user !=  "anon.") {
      $lists = $repo->findBy(['user_id' => $user->getId()]);
      if (count($lists) > 0) {
        $has_lists = true;
      }
      foreach ($lists as $list) {
        //var_dump($list->getName());
        $my_lists[$list->getName()] = $list->getId();
      }
    }


    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => "https://api.themoviedb.org/3/movie/".$id."?api_key=".$api_key,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "Content-Type: application/json",
        "cache-control: no-cache"
      ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
      echo "cURL Error #:" . $err;
    } else {
      $result = json_decode($response, true);
    }

    for ($i = 0; $i < count($result['genres']); $i++) {
      if ($i == 0) {
        $genres .= $result['genres'][$i]['name'];
      } else {
        $genres .= ', '.$result['genres'][$i]['name'];
      }
    }

    $arr_result['title'] = $result["title"];
    $arr_result['release'] = date("d-m-Y", strtotime($result['release_date']));
    $arr_result['genres'] = $genres;
    $arr_result['image'] = "https://image.tmdb.org/t/p/w1280".$result['poster_path'];
    $arr_result['lists'] = $my_lists;
    $arr_result['overview'] = $result['overview'];
    $arr_result['runtime'] = $result['runtime'];

    // list selector
    $choice = array('choice' => null);
    $choices = $this->createFormBuilder($choice)
          ->add('Lists', ChoiceType::class, [
            'choices'  => $my_lists,
          ])
          ->getForm();

    $choices->handleRequest($request);

    if($choices->isSubmitted() && $choices->isValid()) {
      $data = $choices->getData();
      $list = $data["Lists"];
      $this->addAndStay($list, $result['id'],$manager, $repo, $repoJoins);
    }
    //echo 'trois: '.$alert;
    return $this->render('movie/show.html.twig', [
      'movie' => $arr_result,
      'choices' => $choices->createView(),
      'has_lists' => $has_lists,
      'alert' => $alert,
    ]);
  }

}
