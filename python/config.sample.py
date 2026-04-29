"""
CONFIGURATION TEMPLATE
Instructions:
1. Rename this file to 'config.py'.
2. Fill in your local Database credentials and API keys.
3. IMPORTANT: Ensure 'config.py' is added to your .gitignore to prevent 
   sensitive credentials from being leaked to public repositories.
"""

# Database Connection Settings
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'YOUR_PASSWORD',
    'database': 'media_library_system',
    'charset': 'utf8mb4'  # Recommended for supporting Emoji and special characters
}

# Third-Party Service Credentials
# Obtain your key from: https://www.themoviedb.org/settings/api
TMDB_API_KEY = "YOUR_TMDB_API_KEY_HERE"

"""
SECURITY NOTE:
For production environments, consider using environment variables (e.g., python-dotenv)
instead of hardcoding secrets in configuration files.
"""
