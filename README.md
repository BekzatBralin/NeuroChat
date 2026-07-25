# NeuroChat

NeuroChat is an AI-powered conversational web application.

## Technology Stack
- **Backend**: Vanilla PHP with PDO (Database: MariaDB)
- **Frontend**: Vue 3 (Composition API) + Vite + Vanilla CSS
- **Database**: MariaDB

## Installation & Setup

1. **Clone the repository:**
   Clone the repository to your server or local environment.

2. **Database Setup:**
   Ensure you have MariaDB installed. Create a new database and import your schema.

3. **Environment Setup:**
   Navigate to the `backend` directory and copy the `.env.example` file:
   ```bash
   cp backend/.env.example backend/.env
   ```
   Edit `backend/.env` and provide the required keys and database credentials.

4. **Frontend Setup:**
   Navigate to the `frontend` directory, install dependencies, and build:
   ```bash
   cd frontend
   npm install
   npm run build
   ```

5. **Deployment:**
   You can use the provided `deploy.sh` script in the root directory to build the frontend and sync files to your production web server path (e.g., `/var/www/neurochat/`).

## Architecture Highlights
- Uses a `tts.php` proxy to connect with upstream TTS gateways.
- Uses `stream.php` to handle Server-Sent Events (SSE) for streaming model responses.
- Supports comprehensive custom TTS settings (including language-specific voice and role configuration).
- Includes an Admin panel for configuration and user management.
