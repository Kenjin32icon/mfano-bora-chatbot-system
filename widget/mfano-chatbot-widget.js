/**
 * Mfano Bora Africa Chatbot Widget
 * Vanilla JS - no React/Node build step required, so it drops straight
 * into the existing HTML/CSS/JS/PHP website.
 *
 * Configure the endpoint by setting window.MFANO_CHAT_API before this
 * script loads, e.g.:
 *   <script>window.MFANO_CHAT_API = "https://www.mfanoboraafrica.com/chatbot/api/chat.php";</script>
 */
(function () {
  const API_URL = window.MFANO_CHAT_API || '/chatbot/api/chat.php';
  const SESSION_KEY = 'mfano_chat_session_id';

  function getSessionId() {
    let id = localStorage.getItem(SESSION_KEY);
    if (!id) {
      id = 'sess_' + Math.random().toString(36).slice(2) + Date.now();
      localStorage.setItem(SESSION_KEY, id);
    }
    return id;
  }

  function buildWidget() {
    const root = document.createElement('div');
    root.id = 'mfano-chat-root';
    root.innerHTML = `
      <button id="mfano-chat-launcher" aria-label="Open chat">💬</button>
      <div id="mfano-chat-window">
        <div id="mfano-chat-header">
          <div>
            <h3>Mfano Bora Assistant</h3>
            <div class="sub">Ask about attachments, careers, awards & more</div>
          </div>
          <button id="mfano-chat-close" aria-label="Close chat">&times;</button>
        </div>
        <div id="mfano-chat-messages"></div>
        <div id="mfano-chat-typing" style="display:none;">Assistant is typing…</div>
        <div id="mfano-chat-input-row">
          <input id="mfano-chat-input" type="text" placeholder="Type your question…" autocomplete="off">
          <button id="mfano-chat-send" aria-label="Send">➤</button>
        </div>
      </div>
    `;
    document.body.appendChild(root);

    const launcher = document.getElementById('mfano-chat-launcher');
    const win = document.getElementById('mfano-chat-window');
    const closeBtn = document.getElementById('mfano-chat-close');
    const input = document.getElementById('mfano-chat-input');
    const sendBtn = document.getElementById('mfano-chat-send');
    const messages = document.getElementById('mfano-chat-messages');
    const typing = document.getElementById('mfano-chat-typing');

    launcher.addEventListener('click', () => {
      win.classList.toggle('open');
      if (win.classList.contains('open') && messages.children.length === 0) {
        addMessage(
          "Hi! I'm the Mfano Bora Africa assistant. Ask me about attachments, careers, ICT training, transport & logistics, or the Road Safety Awards.",
          'bot'
        );
      }
    });
    closeBtn.addEventListener('click', () => win.classList.remove('open'));

    function addMessage(text, role, sources, isFallback) {
      const div = document.createElement('div');
      div.className = 'mfano-msg ' + role + (isFallback ? ' fallback' : '');
      div.textContent = text;
      if (sources && sources.length) {
        const src = document.createElement('div');
        src.className = 'mfano-sources';
        src.textContent = 'Sources: ' + sources.join(', ');
        div.appendChild(src);
      }
      messages.appendChild(div);
      messages.scrollTop = messages.scrollHeight;
    }

    async function sendMessage() {
      const text = input.value.trim();
      if (!text) return;
      addMessage(text, 'user');
      input.value = '';
      typing.style.display = 'block';

      try {
        const res = await fetch(API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message: text, session_id: getSessionId() }),
        });
        const data = await res.json();
        typing.style.display = 'none';
        addMessage(
          data.answer || 'Sorry, something went wrong.',
          'bot',
          data.sources,
          data.fallback
        );
      } catch (err) {
        typing.style.display = 'none';
        addMessage('Connection error. Please try again shortly.', 'bot', [], true);
      }
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') sendMessage();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildWidget);
  } else {
    buildWidget();
  }
})();
