-- =====================================================
-- MovieDB — Complete Database Setup Script
-- =====================================================
-- 
-- This script creates the MovieDB database, all tables,
-- and inserts sample data for testing.
--
-- HOW TO USE:
--   1. Open phpMyAdmin (http://localhost/phpmyadmin)
--   2. Click the "SQL" tab at the top
--   3. Paste this entire script
--   4. Click "Go" to run it
--
-- Default Admin Account:
--   Username: admin
--   Password: admin123
-- =====================================================

-- ----- Create the database -----
CREATE DATABASE IF NOT EXISTS moviedb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE moviedb;

-- ----- Drop existing tables (in reverse dependency order) -----
-- We drop tables that depend on others first to avoid errors.
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS movie_genres;
DROP TABLE IF EXISTS movies;
DROP TABLE IF EXISTS genres;
DROP TABLE IF EXISTS users;

-- =====================================================
-- TABLE: users
-- Stores registered user accounts.
-- The 'role' column determines access level.
-- =====================================================
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: genres
-- Stores movie genres/categories.
-- =====================================================
CREATE TABLE genres (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: movies
-- Stores movie information.
-- poster stores the filename (e.g., "inception.jpg").
-- cast_members is a simple text field with comma-separated names.
-- =====================================================
CREATE TABLE movies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    synopsis     TEXT,
    release_date DATE,
    director     VARCHAR(100),
    cast_members TEXT,
    runtime      INT COMMENT 'Runtime in minutes',
    poster       VARCHAR(255) DEFAULT NULL COMMENT 'Poster image filename',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: movie_genres (junction / many-to-many)
-- Links movies to their genres.
-- A movie can have multiple genres, and a genre can
-- belong to multiple movies.
-- =====================================================
CREATE TABLE movie_genres (
    movie_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (movie_id, genre_id),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: ratings
-- Stores user ratings for movies (1 to 10).
-- Each user can rate a movie only ONCE (UNIQUE constraint).
-- =====================================================
CREATE TABLE ratings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    movie_id   INT NOT NULL,
    rating     TINYINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_movie_rating (user_id, movie_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    CONSTRAINT chk_rating_range CHECK (rating >= 1 AND rating <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: reviews
-- Stores user-written reviews for movies.
-- Each user can review a movie only ONCE (UNIQUE constraint).
-- =====================================================
CREATE TABLE reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    movie_id    INT NOT NULL,
    review_text TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_movie_review (user_id, movie_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================
-- INSERT: Default Admin Account
-- =====================================================
-- Password "admin123" hashed with PHP password_hash()
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@moviedb.com', '$2y$10$aTYLu7apH0TRjQIvofmeve7HnGOwNqeL8Du8n6Fwh69xhbPWgALwS', 'admin');


-- =====================================================
-- INSERT: Genres
-- =====================================================
INSERT INTO genres (name) VALUES
('Action'),
('Drama'),
('Comedy'),
('Sci-Fi'),
('Horror'),
('Thriller'),
('Romance'),
('Animation'),
('Adventure'),
('Mystery');


-- =====================================================
-- INSERT: Sample Movies
-- =====================================================
INSERT INTO movies (title, synopsis, release_date, director, cast_members, runtime, poster) VALUES

('The Shawshank Redemption',
 'A banker convicted of uxoricide forms a friendship over a quarter century with a hardened convict, while maintaining his innocence and trying to remain hopeful through simple compassion.',
 '1994-09-23', 'Frank Darabont',
 'Tim Robbins, Morgan Freeman, Bob Gunton, William Sadler',
 142, NULL),

('Inception',
 'A thief who steals corporate secrets through the use of dream-sharing technology is given the inverse task of planting an idea into the mind of a C.E.O., but his tragic past may doom the project and his team to disaster.',
 '2010-07-16', 'Christopher Nolan',
 'Leonardo DiCaprio, Joseph Gordon-Levitt, Elliot Page, Tom Hardy',
 148, NULL),

('The Dark Knight',
 'When the menace known as the Joker wreaks havoc and chaos on the people of Gotham, Batman must accept one of the greatest psychological and physical tests of his ability to fight injustice.',
 '2008-07-18', 'Christopher Nolan',
 'Christian Bale, Heath Ledger, Aaron Eckhart, Michael Caine',
 152, NULL),

('Pulp Fiction',
 'The lives of two mob hitmen, a boxer, a gangster and his wife, and a pair of diner bandits intertwine in four tales of violence and redemption.',
 '1994-10-14', 'Quentin Tarantino',
 'John Travolta, Uma Thurman, Samuel L. Jackson, Bruce Willis',
 154, NULL),

('Forrest Gump',
 'The history of the United States from the 1950s to the 70s unfolds from the perspective of an Alabama man with an IQ of 75, who yearns to be reunited with his childhood sweetheart.',
 '1994-07-06', 'Robert Zemeckis',
 'Tom Hanks, Robin Wright, Gary Sinise, Sally Field',
 142, NULL),

('The Matrix',
 'When a beautiful stranger leads computer hacker Neo to a forbidding underworld, he discovers the shocking truth — the life he knows is the elaborate deception of an evil cyber-intelligence.',
 '1999-03-31', 'Lana Wachowski, Lilly Wachowski',
 'Keanu Reeves, Laurence Fishburne, Carrie-Anne Moss, Hugo Weaving',
 136, 'poster_6.jpg'),

('Spirited Away',
 'During her family\'s move to the suburbs, a sullen 10-year-old girl wanders into a world ruled by gods, witches, and spirits, and where humans are changed into beasts.',
 '2001-07-20', 'Hayao Miyazaki',
 'Rumi Hiiragi, Miyu Irino, Mari Natsuki, Takeshi Naito',
 125, 'poster_7.jpg'),

('Interstellar',
 'When Earth becomes uninhabitable in the future, a farmer and ex-NASA pilot is tasked with piloting a spacecraft, along with a team of researchers, to find a new planet for humans.',
 '2014-11-07', 'Christopher Nolan',
 'Matthew McConaughey, Anne Hathaway, Jessica Chastain, Michael Caine',
 169, 'poster_8.jpg'),

('Parasite',
 'Greed and class discrimination threaten the newly formed symbiotic relationship between the wealthy Park family and the destitute Kim clan.',
 '2019-05-30', 'Bong Joon-ho',
 'Song Kang-ho, Lee Sun-kyun, Cho Yeo-jeong, Choi Woo-shik',
 132, 'poster_9.jpg'),

('The Silence of the Lambs',
 'A young F.B.I. cadet must receive the help of an incarcerated and manipulative cannibal killer to help catch another serial killer, a madman who skins his victims.',
 '1991-02-14', 'Jonathan Demme',
 'Jodie Foster, Anthony Hopkins, Scott Glenn, Ted Levine',
 118, 'poster_10.jpg');


-- =====================================================
-- INSERT: Movie-Genre Relationships
-- =====================================================
-- Movie 1: The Shawshank Redemption → Drama
INSERT INTO movie_genres (movie_id, genre_id) VALUES (1, 2);

-- Movie 2: Inception → Action, Sci-Fi, Thriller
INSERT INTO movie_genres (movie_id, genre_id) VALUES (2, 1);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (2, 4);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (2, 6);

-- Movie 3: The Dark Knight → Action, Drama, Thriller
INSERT INTO movie_genres (movie_id, genre_id) VALUES (3, 1);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (3, 2);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (3, 6);

-- Movie 4: Pulp Fiction → Drama, Thriller
INSERT INTO movie_genres (movie_id, genre_id) VALUES (4, 2);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (4, 6);

-- Movie 5: Forrest Gump → Drama, Comedy, Romance
INSERT INTO movie_genres (movie_id, genre_id) VALUES (5, 2);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (5, 3);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (5, 7);

-- Movie 6: The Matrix → Action, Sci-Fi
INSERT INTO movie_genres (movie_id, genre_id) VALUES (6, 1);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (6, 4);

-- Movie 7: Spirited Away → Animation, Adventure
INSERT INTO movie_genres (movie_id, genre_id) VALUES (7, 8);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (7, 9);

-- Movie 8: Interstellar → Sci-Fi, Drama, Adventure
INSERT INTO movie_genres (movie_id, genre_id) VALUES (8, 4);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (8, 2);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (8, 9);

-- Movie 9: Parasite → Drama, Thriller, Comedy
INSERT INTO movie_genres (movie_id, genre_id) VALUES (9, 2);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (9, 6);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (9, 3);

-- Movie 10: The Silence of the Lambs → Thriller, Horror, Mystery
INSERT INTO movie_genres (movie_id, genre_id) VALUES (10, 6);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (10, 5);
INSERT INTO movie_genres (movie_id, genre_id) VALUES (10, 10);


-- =====================================================
-- DONE!
-- =====================================================
-- The database is now ready. You should see:
--   - 6 tables created
--   - 1 admin user
--   - 10 genres
--   - 10 sample movies with genre assignments
-- =====================================================
