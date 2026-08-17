
# Machine Learning-Based Web-Integrated Chatbot for Mfano Bora Africa Ltd

## 📖 Project Overview

This project is an intelligent, event-driven chatbot designed to act as a frontline digital assistant for Mfano Bora Africa Ltd. The primary purpose of the system is to instantly resolve visitor queries, guide candidates to attachment and career portals, explain logistics consulting services, and seamlessly integrate into the existing website UI.

To ensure continuous learning while completely eliminating hallucinations, the system utilises a Retrieval-Augmented Generation (RAG) architecture. This restricts the Large Language Model (LLM) to generating answers derived exclusively from the verified Mfano Bora knowledge base.

---

## 🚀 Key Features

* **Targeted User Routing:** Successfully directs specific user profiles—such as corporate clients, students, and community members—to appropriate solutions, contact information, or web pages.
* **Zero-Hallucination Responses:** Employs a "Single Source of Truth" (SSOT) model ensuring all chatbot responses are grounded in verified organizational data.
* **Fallback Protocol:** If information is missing from the knowledge base, the bot provides Mfano Bora's official contact details (info@mfanoboraafrica.com) and physical location.
* **Comprehensive Logging:** All user interactions are logged into a PostgreSQL database to allow system administrators to assess information quality, track frequent queries, and manage the knowledge base.

---

## 🛠️ Technology Stack & AI Architecture

* **Large Language Model (LLM):** Groq API utilizing a highly performant open-source model like Llama-3 (e.g., Llama-3.3-70b) for lightning-fast inference and high reasoning capabilities.
* **NLP & Embeddings:** HuggingFace Sentence Transformers (via Python) to convert website scraped data and FAQs into vector embeddings for semantic search.
* **Vector Database:** PostgreSQL with the `pgvector` extension to store and retrieve document chunks based on user similarity scores.
* **Backend & API Routing:** Node.js with Express.js (or Python FastAPI) to handle stateless API requests, webhook integrations, and data formatting.
* **Frontend UI:** React.js integrated with Tailwind CSS to build a lightweight, responsive floating chat widget.
* **Security & Admin Auth:** Firebase Authentication coupled with strict Role-Based Access Control (RBAC) to protect the admin dashboard and system audit logs.

---

## 📂 Project Directory Structure

For ease of deployment, maintenance, and scaling, the repository structure of the project is modular:

* `ai-pipeline/`: Contains the Python Machine Learning and RAG Architecture scripts.
* `data-cleaning/`: Python scripts responsible for removing duplicate HTML tags and cleaning scraped Mfano Bora data.
* `embeddings/`: HuggingFace vectorisation scripts that convert text into numerical models.
* `knowledge-base/`: Staging directory for raw, verified JSON/CSV data files before they are pushed to the database.
* `rag_engine.py`: Core logic for semantic search and Groq LLM parsing.


* `backend-api/`: Node.js / Express.js server containing the logic for handling routes, API endpoints, and database models.
* `frontend-widget/`: React.js chatbot UI module, including components for the floating button and chat window.
* `admin-dashboard/`: React.js internal portal featuring the analytics dashboard, knowledge base editor, and chat logs.
* `database/`: Contains SQL initialization scripts, notably `init_schema.sql`, to create PostgreSQL tables and setup `pgvector`.
* `Docs/`: Houses project documentation, research assignments, and implementation plans.


* `docker-compose.yml`: Configuration file for containerization to allow for unified deployment.

---

## 🔄 Data Pipeline & Workflow

To integrate identified sources into the chatbot's machine learning backend without incurring hallucination, an automated Data Acquisition Pipeline is implemented:

1. **Extraction:** Using Python web scraping libraries and admin ingestion scripts, raw text data is extracted from Mfano Bora's official web portals and internal repositories.
2. **Cleaning & Chunking:** The raw scraped text is sanitised by removing duplicate HTML tags and old data, then structured into manageable chunks.
3. **Vectorisation:** These chunks are converted to vector form using HuggingFace's Sentence Transformers.
4. **Indexing & Storage:** The vectors and text chunks are stored directly in the PostgreSQL database using the `pgvector` extension within the `knowledge_base` table.
5. **Retrieval & Parsing:** When a user enters a query, the backend runs a similarity search on `pgvector` and passes only the relevant context chunks to the Groq API (Llama-3 model) to generate the response.