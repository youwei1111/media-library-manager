# media-library-manager
A full-stack media management system with API integration for movies, books, and manga.

## 🚀 Key Features

* **Multi-API Integration**: Seamlessly integrated with **TMDB API** (Movies/TV), **Bangumi API** (Anime/Manga), and **Google Books API** (Books) to provide comprehensive media metadata.
* **Asynchronous Interaction (AJAX)**: Enhanced user experience with no-refresh operations, including real-time item addition, deletion, progress tracking, and auto-saving features.
* **Hybrid Backend Architecture**: Leverages **Python** scripts for complex data scraping and processing, while **PHP** handles robust server-side logic and data persistence.
* **Responsive & Adaptive UI**: Modern interface with full **Dark Mode** support for a comfortable viewing experience across different devices.

## 🛠️ Tech Stack

* **Frontend**: HTML5, CSS3, JavaScript (Vanilla JS)
* **Backend**: PHP, Python (Requests, PyMySQL)
* **Database**: MySQL

## 🛠️ Installation & Setup

1. Clone the repository.
2. Create db_config.php from db_config.sample.php and enter your database credentials.
3. Create config.py from config.sample.py and add your TMDB API Key.
4. Import database.sql into your MySQL server.
