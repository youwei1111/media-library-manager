import requests
import pymysql
import sys
import json

# 1. 数据库配置
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',  # XAMPP 默认密码为空
    'database': 'media_library_system'
}

# --- 功能函数：抓取多个候选结果 ---

def get_search_results(name, media_type):
    results = []
    
    if media_type in ['movie', 'tv', 'show', 'anime']:
        api_key = "57e82c8a91b54f2fb743ef8f5133061b" 
        url = f"https://api.themoviedb.org/3/search/multi?api_key={api_key}&query={name}&language=zh-CN"
        try:
            res = requests.get(url, timeout=10).json()
            for item in res.get('results', [])[:10]:
                title = item.get('title') or item.get('name')
                poster_path = item.get('poster_path')
                results.append({
                    "title": title,
                    "rating": round(item.get('vote_average', 0), 1),
                    "poster": f"https://image.tmdb.org/t/p/w500{poster_path}" if poster_path else "",
                    "summary": item.get('overview', '暂无简介'),
                    "type": media_type
                })
        except Exception as e:
            print(f"TMDB 搜索失败: {e}", file=sys.stderr)

    elif media_type == 'manga':
        headers = {'User-Agent': 'MyMediaLibrary/1.0'}
        search_url = f"https://api.bgm.tv/search/subject/{name}?type=1"
        try:
            res = requests.get(search_url, headers=headers, timeout=10).json()
            if res.get('list'):
                for item in res['list'][:10]:
                    subject_id = item.get('id')
                    detail_url = f"https://api.bgm.tv/v0/subjects/{subject_id}"
                    detail_res = requests.get(detail_url, headers=headers, timeout=5).json()
                    score = detail_res.get('rating', {}).get('score', 0)
                    images = detail_res.get('images', {})
                    results.append({
                        "title": detail_res.get('name_cn') or detail_res.get('name'),
                        "rating": float(score) if score else 0.0,
                        "poster": images.get('large') or images.get('common') or "",
                        "summary": detail_res.get('summary', '暂无简介'),
                        "type": 'manga'
                    })
        except Exception as e:
            print(f"Bangumi 深度查询失败: {e}", file=sys.stderr)

    elif media_type == 'book':
        headers = {'User-Agent': 'Mozilla/5.0'}
        google_url = f"https://www.googleapis.com/books/v1/volumes?q=intitle:{name}&maxResults=8&langRestrict=zh"
        try:
            response = requests.get(google_url, headers=headers, timeout=10)
            res = response.json()
            if 'items' in res:
                for item in res['items']:
                    vol = item.get('volumeInfo', {})
                    # 统一使用 rating 键名
                    raw_score = vol.get('averageRating', 0)
                    score = float(raw_score) * 2 if raw_score else 0.0
                    images = vol.get('imageLinks', {})
                    poster = images.get('thumbnail') or images.get('smallThumbnail') or ""
                    if poster.startswith('http:'):
                        poster = poster.replace('http:', 'https:')
                    results.append({
                        "title": vol.get('title'),
                        "rating": score,
                        "poster": poster,
                        "summary": f"作者: {', '.join(vol.get('authors', ['未知']))}",
                        "type": 'book'
                    })
            if not results:
                # Open Library 备选
                open_url = f"https://openlibrary.org/search.json?title={name}&limit=5"
                ol_res = requests.get(open_url, timeout=10).json()
                for doc in ol_res.get('docs', []):
                    cover_id = doc.get('cover_i')
                    results.append({
                        "title": doc.get('title'),
                        "rating": 0.0,
                        "poster": f"https://covers.openlibrary.org/b/id/{cover_id}-L.jpg" if cover_id else "",
                        "summary": f"作者: {', '.join(doc.get('author_name', ['未知']))}",
                        "type": 'book'
                    })
        except Exception as e:
            print(f"Book Search Error: {e}", file=sys.stderr)
                
    return results

# --- 功能函数：执行存入数据库 ---
def save_to_mysql(data):
    try:
        db = pymysql.connect(**DB_CONFIG)
        cursor = db.cursor()
        
        # 核心修正：确保字段名和你的数据库表一致。
        # 如果你的数据库表里图片列叫 poster，请把下面的 poster_url 改为 poster
        sql = """
            INSERT INTO media_items (title, type, status, rating, poster_url) 
            VALUES (%s, %s, %s, %s, %s)
        """
        
        # 强制处理评分为浮点数，防止书籍评分变成字符串导致写入失败
        rating_value = float(data.get('rating', 0))
        
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
        # 这里会打印具体的 SQL 错误，如果字段名不对，你会在这里看到报错
        print(f"数据库写入失败: {e}", file=sys.stderr)
        return False

# --- 程序入口 ---
def main():
    if len(sys.argv) > 3 and sys.argv[1] == "--search":
        name = sys.argv[2]
        m_type = sys.argv[3]
        results = get_search_results(name, m_type)
        print(json.dumps(results, ensure_ascii=False))

    elif len(sys.argv) > 2 and sys.argv[1] == "--add":
        try:
            selected_json = sys.argv[2]
            selected_data = json.loads(selected_json)
            # 这里的 SUCCESS 会被 PHP 接收
            if save_to_mysql(selected_data):
                print("SUCCESS")
            else:
                print("FAILED")
        except Exception as e:
            print(f"JSON解析或处理失败: {e}", file=sys.stderr)
            print("FAILED")
    else:
        # 命令行直接运行测试
        print("=== 调试模式 ===")
        results = get_search_results("博弈论", "book")
        for r in results:
            print(r)

if __name__ == "__main__":
    main()