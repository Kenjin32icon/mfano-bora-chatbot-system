"""
Mfano Bora Africa — Automated Web Scraper
Task 3: Information Collection
Task 4: Information Classification (category tagging happens here)

Scrapes the verified primary sources identified in Task 2, cleans the
HTML, chunks the text, tags each chunk with a category + target user
group + update frequency, and stages the result as JSON in
ai-pipeline/knowledge-base/ for the embeddings step to pick up.
"""

import json
import os
import re
import time
from datetime import datetime, timezone

import requests
from bs4 import BeautifulSoup

# ---------------------------------------------------------------------------
# 1. SOURCE REGISTRY — from Task 2 (Information Sources Identification)
#    Each entry drives Task 4's classification automatically.
# ---------------------------------------------------------------------------
SOURCES = [
    {
        "url": "https://www.mfanoboraafrica.com/",
        "category": "Corporate Profile & Logistics Services",
        "target_users": "Corporate Clients & Logistics Prospects",
        "update_freq": "Monthly",
    },
    {
        "url": "https://www.mfanoboraafrica.com/apply-now/",
        "category": "Careers & Attachments",
        "target_users": "Students & Graduates",
        "update_freq": "Quarterly",
    },
    {
        "url": "https://www.mfanoboraafrica.com/gallery/",
        "category": "Awards & Road Safety Records",
        "target_users": "Nominees, Industry Peers & Community Members",
        "update_freq": "Bi-Annually",
    },
    {
        "url": "https://www.mfanoboraafrica.com/contact-us/",
        "category": "Location & Contact Directory",
        "target_users": "All Visiting Website Users",
        "update_freq": "As Needed",
    },
]

HEADERS = {
    "User-Agent": "MfanoBoraChatbotKnowledgeBaseBot/1.0 (+info@mfanoboraafrica.com)"
}

OUTPUT_DIR = os.path.join(
    os.path.dirname(__file__), "..", "knowledge-base"
)
OUTPUT_FILE = os.path.join(OUTPUT_DIR, "raw_knowledge_chunks.json")

CHUNK_SIZE_WORDS = 150   # words per chunk
CHUNK_OVERLAP = 30       # word overlap between chunks, for retrieval context


# ---------------------------------------------------------------------------
# 2. EXTRACTION
# ---------------------------------------------------------------------------
def fetch_page(url: str) -> str:
    """Downloads a page and returns its raw HTML, with basic retry logic."""
    for attempt in range(3):
        try:
            response = requests.get(url, headers=HEADERS, timeout=15)
            response.raise_for_status()
            return response.text
        except requests.RequestException as exc:
            print(f"  [warn] attempt {attempt + 1} failed for {url}: {exc}")
            time.sleep(2)
    raise RuntimeError(f"Could not fetch {url} after 3 attempts")


# ---------------------------------------------------------------------------
# 3. CLEANING — strip tags, scripts, nav/footer noise, duplicate whitespace
# ---------------------------------------------------------------------------
def clean_html(html: str) -> str:
    soup = BeautifulSoup(html, "lxml")

    for tag in soup(["script", "style", "nav", "footer", "noscript", "svg"]):
        tag.decompose()

    text = soup.get_text(separator=" ")
    text = re.sub(r"\s+", " ", text).strip()
    return text


# ---------------------------------------------------------------------------
# 4. CHUNKING — sliding window over cleaned text
# ---------------------------------------------------------------------------
def chunk_text(text: str, chunk_size=CHUNK_SIZE_WORDS, overlap=CHUNK_OVERLAP):
    words = text.split()
    if not words:
        return []

    chunks = []
    start = 0
    while start < len(words):
        end = start + chunk_size
        chunk = " ".join(words[start:end])
        if len(chunk.split()) > 20:  # skip near-empty trailing fragments
            chunks.append(chunk)
        start += chunk_size - overlap
    return chunks


# ---------------------------------------------------------------------------
# 5. MAIN PIPELINE
# ---------------------------------------------------------------------------
def run_scraper():
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    all_records = []

    for source in SOURCES:
        print(f"[scraping] {source['url']}")
        try:
            html = fetch_page(source["url"])
        except RuntimeError as exc:
            print(f"  [error] skipping source: {exc}")
            continue

        cleaned = clean_html(html)
        chunks = chunk_text(cleaned)
        print(f"  -> {len(chunks)} chunks extracted, "
              f"tagged as '{source['category']}'")

        for chunk in chunks:
            all_records.append({
                "source_url": source["url"],
                "category": source["category"],          # Task 4 classification
                "content": chunk,
                "target_users": source["target_users"],
                "update_freq": source["update_freq"],
                "scraped_at": datetime.now(timezone.utc).isoformat(),
            })

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        json.dump(all_records, f, ensure_ascii=False, indent=2)

    print(f"\nSaved {len(all_records)} classified chunks to {OUTPUT_FILE}")
    return all_records


if __name__ == "__main__":
    run_scraper()