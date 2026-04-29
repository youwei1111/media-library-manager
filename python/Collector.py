import requests
import pymysql
import sys
import json

# 1. Configuration Management
try:
    from config import DB_CONFIG, TMDB_API_KEY
except ImportError:
    # Error message for deployment/setup phase
    print("Configuration Error: Please create 'config.py' based on 'config.sample.py'.")
    sys.exit(1)

# --- Feature: Multi-Source Metadata Scraping ---

def get_search_results(name, media_type):
    """
    Fetches candidate results from various external APIs based on media type.
    - TMDB: Movies, TV Shows, Anime
    - Bangumi: Manga
    - Google Books: General Literature
    """
    results = []
    
    # Logic for Movies, TV, and Anime (using TMDB API)
    if media_type in ['movie', 'tv', 'show', 'anime']:
        url = f"https://api.themoviedb.org/3/search/multi?api_key={TMDB_API_KEY}&query={name}&language=en-US"
        try:
            res = requests.get(url, timeout=10).json()
            for item in res.get('results', [])[:10]:
                title = item.get('title') or item.get('name')
                poster_path = item.get('poster_path')
                results.append({
                    "title": title,
                    "rating": round(item.get('vote_average', 0), 1),
                    "poster": f"https://image.tmdb.org/t/p/w500{poster_path}" if poster_path else "",
                    "summary": item.get('overview', 'No description available.'),
                    "type": media_type
                })
        except Exception as e:
            print(f"TMDB API Search Failed: {e}", file=sys.stderr)

    # Logic for Manga (using Bangumi/bgm.tv API)
    elif media_type == 'manga':
        headers = {'User-Agent': 'MyMediaLibrary/1.0 (Python-Requests)'}
        search_url = f"https://api.bgm.tv/search/subject/{name}?type=1"
        try:
            res = requests.get(search_url, headers=headers, timeout=10).json()
            if res.get('list'):
                for item in res['list'][:10]:
                    subject_id = item.get('id')
                    # V0 API provides more detailed metadata
                    detail_url = f"https://api.bgm.tv/v0/subjects/{subject_id}"
                    detail_res = requests.get(detail_url, headers=headers, timeout=5).json()
                    score = detail_res.get('rating', {}).get('score', 0)
                    images = detail_res.get('images', {})
                    results.append({
                        "title": detail_res.get('name_cn') or detail_res.get('name'),
                        "rating": float(score) if score else 0.0,
                        "poster": images.get('large') or images.get('common') or "",
                        "summary": detail_res.get('summary', 'No summary available.'),
                        "type": 'manga'
                    })
        except Exception as e:
            print(f"Bangumi API Deep Query Failed: {e}", file=sys.stderr)

    # Logic for Books (using Google Books API with Open Library as fallback)
    elif media_type == 'book':
        headers = {'User-Agent': 'Mozilla/5.0'}
        google_url = f"https://www.googleapis.com/books/v1/volumes?q=intitle:{name}&maxResults=8"
        try:
            response = requests.get(google_url, headers=headers, timeout=10)
            res = response.json()
            if 'items' in res:
                for item in res['items']:
                    vol = item.get('volumeInfo', {})
                    # Normalizing rating to a 10-point scale
                    raw_score = vol.get('averageRating', 0)
                    score = float(raw_score) * 2 if raw_score else 0.0
                    images = vol.get('imageLinks', {})
                    poster = images.get('thumbnail') or images.get('smallThumbnail') or ""
                    # Enforce HTTPS for secure asset loading
                    if poster.startswith('http:'):
                        poster = poster.replace('http:', 'https:')
                    results.append({
                        "title": vol.get('title'),
                        "rating": score,
                        "poster": poster,
                        "summary": f"Authors: {', '.join(vol.get('authors', ['Unknown']))}",
                        "type": 'book'
                    })
            
            # Fallback to Open Library if Google Books returns empty
            if not results:
                open_url = f"https://openlibrary.org/search.json?title={name}&limit=5"
                ol_res = requests.get(open_url, timeout=10).json()
                for doc in ol_res.get('docs', []):
                    cover_id = doc.get('cover_i')
                    results.append({
                        "title": doc.get('title'),
                        "rating": 0.0,
                        "poster": f"https://covers.openlibrary.org/b/id/{cover_id}-L.jpg" if cover_id else "",
                        "summary": f"Authors: {', '.join(doc.get('author_name', ['Unknown']))}",
                        "type": 'book'
                    })
        except Exception as e:
            print(f"Book Search Service Error: {e}", file=sys.stderr)
                
    return results

# --- Feature: Data Persistence (MySQL) ---

def save_to_mysql(data):
    """
    Inserts validated metadata into the MySQL database.
    """
    try:
        db = pymysql.connect(**DB_CONFIG)
        cursor = db.cursor()
        
        # Schema Mapping: Ensure field names match your 'media_items' table structure
        sql = """
            INSERT INTO media_items (title, type, status, rating, poster_url) 
            VALUES (%s, %s, %s, %s, %s)
        """
        
        # Data Normalization: Ensure numeric values are float type for DB compatibility
        rating_value = float(data.get('rating', 0))
        
        # 'Plan to Watch' (想看) is the default status for new additions
        data_to_insert = (
            data['title'],
            data['type'],
            '想看', 
            rating_value,
            data.get('poster') or ""
        )
        
        cursor.execute(sql, data_to_insert)
        db.commit()
        db.close()
        return True
    except Exception as e:
        print(f"Database Persistence Failed: {e}", file=sys.stderr)
        return False

# --- Entry Point: Command Line Interface (CLI) ---

def main():
    # Scenario A: Invoked by PHP to search for items
    if len(sys.argv) > 3 and sys.argv[1] == "--search":
        name = sys.argv[2]
        m_type = sys.argv[3]
        results = get_search_results(name, m_type)
        # Output as JSON for PHP's json_decode
        print(json.dumps(results, ensure_ascii=False))

    # Scenario B: Invoked by PHP to add a confirmed item to DB
    elif len(sys.argv) > 2 and sys.argv[1] == "--add":
        try:
            selected_json = sys.argv[2]
            selected_data = json.loads(selected_json)
            # Response caught by PHP bridge for UI feedback
            if save_to_mysql(selected_data):
                print("SUCCESS")
            else:
                print("FAILED")
        except Exception as e:
            print(f"Critical Error in Data Processing: {e}", file=sys.stderr)
            print("FAILED")
    
    # Default: Diagnostic Mode
    else:
        print("=== Diagnostic Mode ===")
        results = get_search_results("The Great Gatsby", "book")
        for r in results:
            print(r)

if __name__ == "__main__":
    main()
