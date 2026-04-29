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

   elif media_type == 'book':
        # Use a standard browser User-Agent to prevent request blocking
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        
        # Pre-process query: strip whitespace and hyphens to normalize ISBN strings
        clean_query = name.strip().replace('-', '')
        is_isbn = clean_query.isdigit() and len(clean_query) in [10, 13]
        
        try:
            # --- STEP 1: Precise ISBN Lookup via OpenLibrary API ---
            if is_isbn:
                # Using the OpenLibrary 'data' API for structured metadata retrieval
                isbn_url = f"https://openlibrary.org/api/books?bibkeys=ISBN:{clean_query}&format=json&jscmd=data"
                res = requests.get(isbn_url, timeout=8).json()
                key = f"ISBN:{clean_query}"
                
                if key in res:
                    data = res[key]
                    results.append({
                        "title": data.get('title'),
                        "rating": 0.0,
                        "poster": data.get('cover', {}).get('large', ""),
                        "summary": f"[ISBN Match] Authors: {', '.join([a['name'] for a in data.get('authors', [])]) if data.get('authors') else 'Unknown'}",
                        "type": 'book'
                    })

            # --- STEP 2: General Keyword Search (OpenLibrary Hybrid) ---
            # Fallback to search index if results are insufficient
            if len(results) < 5:
                ol_search_url = f"https://openlibrary.org/search.json?q={name}&limit=5"
                ol_res = requests.get(ol_search_url, headers=headers, timeout=10).json()
                
                for doc in ol_res.get('docs', []):
                    # Basic Deduplication: Skip if the title already exists in results
                    if any(r['title'].lower() == doc.get('title', '').lower() for r in results):
                        continue
                        
                    cover_id = doc.get('cover_i')
                    results.append({
                        "title": doc.get('title'),
                        "rating": 0.0,
                        "poster": f"https://covers.openlibrary.org/b/id/{cover_id}-L.jpg" if cover_id else "",
                        "summary": f"Authors: {', '.join(doc.get('author_name', ['Unknown']))}",
                        "type": 'book'
                    })

            # --- STEP 3: Deep Supplement via Google Books API ---
            # Google Books provides excellent coverage for non-English (especially Chinese) titles
            if len(results) < 8:
                # Use a broad query to maximize discovery
                google_url = f"https://www.googleapis.com/books/v1/volumes?q={name}&maxResults=5"
                g_response = requests.get(google_url, headers=headers, timeout=8)
                
                if g_response.status_code == 200:
                    g_res = g_response.json()
                    if 'items' in g_res:
                        for item in g_res['items']:
                            vol = item.get('volumeInfo', {})
                            g_title = vol.get('title')
                            
                            # Deduplication check
                            if any(r['title'].lower() == g_title.lower() for r in results):
                                continue
                            
                            raw_score = vol.get('averageRating', 0)
                            images = vol.get('imageLinks', {})
                            
                            results.append({
                                "title": g_title,
                                # Convert 5-star rating system to 10-point scale
                                "rating": float(raw_score) * 2 if raw_score else 0.0,
                                "poster": (images.get('thumbnail') or "").replace('http:', 'https:'),
                                "summary": f"Source: Google | Authors: {', '.join(vol.get('authors', ['Unknown']))}",
                                "type": 'book'
                            })

        except Exception as e:
            # Error logging for debugging purposes
            print(f"Book Hybrid Search Error: {e}", file=sys.stderr)
                
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
