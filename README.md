# Movies Lists

The goal of this project was to make a web app allowing users to create lists of movies, with the help of a movies database API.

## Functionalities
The database of the project includes a users tables (with FOsuer Bundle), a lists table, and a joins table (to join a list_id with a movie_id) 

### User
The user can register, log in, edit his details and delete his account

### Lists
The user can create a list, edit its name and content, delete it, make it public (to share with the other users)
From any list, the user can add a movie to the favorites.

### Movies
A movie can be added to a list directly from the movie page or via the edit list page, through a search. The search can be made by title, release date, or genre (one type of search only can be made at a time)


## Built With

* [Symfony](https://symfony.com/) - The web framework used
* [The Movie Database](https://www.themoviedb.org/?language=fr) - The Movies API

