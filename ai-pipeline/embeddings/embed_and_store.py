"""
Mfano Bora Africa — Embeddings & Supabase Storage
Task 5: Knowledge Base Development

Reads the classified chunks produced by scraper.py, converts them to
vector embeddings with a HuggingFace Sentence Transformer, and inserts
them into the `knowledge_base` table in Supabase (pgvector).
"""

import json
import os

from dotenv import load_dotenv
from sentence_transformers import SentenceTransformer
from supabase import create_client, Client

# ---------------------------------------------------------------------------
# 1. CONFIG
# ---------------------------------------------------------------------------
load_dotenv()  # reads .env in repo root

SUPABASE_URL = os.environ["SUPABASE_URL"]
SUPABASE_SERVICE_KEY = os.environ["SUPABASE_SERVICE_KEY"]

INPUT_FILE = os.path.join(
    os.path.dirname(__file__), "..", "knowledge-base", "raw_knowledge_chunks.json"
)

# 384-dim model -> matches VECTOR(384) in database/init_schema.sql
EMBEDDING_MODEL_NAME = "all-MiniLM-L6-v2"
BATCH_SIZE = 50


# ---------------------------------------------------------------------------
# 2. LOAD STAGED CHUNKS
# ---------------------------------------------------------------------------
def load_chunks():
    with open(INPUT_FILE, "r", encoding="utf-8") as f:
        return json.load(f)


# ---------------------------------------------------------------------------
# 3. VECTORISE
# ---------------------------------------------------------------------------
def embed_chunks(records, model):
    texts = [r["content"] for r in records]
    print(f"Embedding {len(texts)} chunks with {EMBEDDING_MODEL_NAME}...")
    vectors = model.encode(texts, show_progress_bar=True, batch_size=32)

    for record, vector in zip(records, vectors):
        record["embedding"] = vector.tolist()
    return records


# ---------------------------------------------------------------------------
# 4. STORE IN SUPABASE
# ---------------------------------------------------------------------------
def store_records(records, supabase: Client):
    inserted = 0
    for i in range(0, len(records), BATCH_SIZE):
        batch = records[i:i + BATCH_SIZE]
        payload = [
            {
                "source_url": r["source_url"],
                "category": r["category"],
                "content": r["content"],
                "embedding": r["embedding"],
                "target_users": r["target_users"],
                "update_freq": r["update_freq"],
            }
            for r in batch
        ]
        response = supabase.table("knowledge_base").insert(payload).execute()
        inserted += len(response.data)
        print(f"  inserted batch {i // BATCH_SIZE + 1}: "
              f"{len(response.data)} rows (running total {inserted})")

    return inserted


# ---------------------------------------------------------------------------
# 5. MAIN
# ---------------------------------------------------------------------------
def run():
    records = load_chunks()
    if not records:
        print("No chunks found. Run scraper.py first.")
        return

    model = SentenceTransformer(EMBEDDING_MODEL_NAME)
    records = embed_chunks(records, model)

    supabase = create_client(SUPABASE_URL, SUPABASE_SERVICE_KEY)
    total = store_records(records, supabase)

    print(f"\nDone. {total} knowledge base rows stored in Supabase.")


if __name__ == "__main__":
    run()