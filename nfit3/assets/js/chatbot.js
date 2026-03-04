/* Chatbot widget with delayed intro and simple FAQ routing */
(function () {
  const storageKey = 'nfChatbotAutoSeen';
  const knowledgeBase = [
    {
      key: 'plans',
      keywords: ['plano', 'planos', 'combo', 'pacote', 'formato'],
      response: {
        text: 'Temos 3 formatos principais: Essencial (nutri\u00e7\u00e3o completa), Performance (nutri\u00e7\u00e3o + treino) e VIP (ajustes semanais + concierge). Todos incluem painel com ajustes da Marina e relat\u00f3rios no painel/chat.',
        cta: { label: 'Ver bastidores dos planos', url: '/services' }
      }
    },
    {
      key: 'pricing',
      keywords: ['preco', 'pre\u00e7o', 'valor', 'investimento', 'custa', 'quanto', 'mensalidade'],
      response: {
        text: 'Os planos completos come\u00e7am em R$ 297/m\u00eas (nutri\u00e7\u00e3o com ajustes mensais) e chegam a R$ 497/m\u00eas no acompanhamento VIP. Na p\u00e1gina de pre\u00e7os voc\u00ea compara cada etapa e consegue ativar seu acesso em 2 minutos.',
        cta: { label: 'Consultar planos e pre\u00e7os', url: 'https://nutremfit.com.br/pricing' }
      }
    },
    {
      key: 'support',
      keywords: ['contato', 'atendimento', 'suporte', 'ajuda', 'humano', 'telefone'],
      response: {
        text: 'Quer falar com a equipe? Abrimos chamados pela \u00c1rea do Aluno ou formul\u00e1rio e respondemos r\u00e1pido. Me conte seu caso que j\u00e1 direciono.',
        cta: { label: 'Abrir atendimento', url: '/contact' }
      }
    },
    {
      key: 'student-area',
      keywords: ['area', 'aluno', 'login', 'acesso', 'plataforma'],
      response: {
        text: 'Assim que confirma a inscri\u00e7\u00e3o voc\u00ea recebe login para a \u00c1rea do Aluno com planos, aulas e check-ins. Se j\u00e1 for aluno basta acessar com seu e-mail e senha.',
        cta: { label: 'Ir para a \u00c1rea do Aluno', url: '/area' }
      }
    },
    {
      key: 'assessment',
      keywords: ['avaliacao', 'avalia\u00e7\u00e3o', 'diagnostico', 'teste', 'questionario'],
      response: {
        text: 'Come\u00e7amos com uma avalia\u00e7\u00e3o inicial gratuita: voc\u00ea envia rotina, hist\u00f3rico e exames e eu mesma monto o rascunho do plano antes da primeira cobran\u00e7a.',
        cta: { label: 'Agendar avalia\u00e7\u00e3o gratuita', url: '/contact' }
      }
    }
  ];

  const quickReplies = knowledgeBase.reduce(function (acc, item) {
    if (item.key) {
      acc[item.key] = item.response;
    }
    return acc;
  }, {});

  const fallbackResponse = {
    text: 'Eu sou a NFit Pulse, assistente virtual da NutremFit. Voc\u00ea responde um question\u00e1rio r\u00e1pido, recebe o plano inicial e o time humano ajusta tudo semanalmente. Quer falar com algu\u00e9m agora?',
    cta: { label: 'Chamar atendimento humano', url: '/contact' }
  };

  const safeSession = {
    get: function (key) {
      try {
        return window.sessionStorage.getItem(key);
      } catch (error) {
        return null;
      }
    },
    set: function (key, value) {
      try {
        window.sessionStorage.setItem(key, value);
      } catch (error) {
        // storage not available, ignore
      }
    }
  };

  function normalizeText(text) {
    return text
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  document.addEventListener('DOMContentLoaded', function () {
    const widget = document.querySelector('[data-nf-chatbot]');
    if (!widget) {
      return;
    }

    const launcher = widget.querySelector('[data-nf-chatbot-launcher]');
    const closeBtn = widget.querySelector('[data-nf-chatbot-close]');
    const windowEl = widget.querySelector('.nf-chatbot__window');
    const messagesEl = widget.querySelector('[data-nf-chatbot-messages]');
    const typingEl = widget.querySelector('[data-nf-typing]');
    const form = widget.querySelector('[data-nf-chatbot-form]');
    const input = widget.querySelector('[data-nf-chatbot-input]');
    const quickButtons = widget.querySelectorAll('[data-nf-question]');
    const delayAttr = parseInt(widget.getAttribute('data-nf-delay'), 10);
    const autoDelay = Number.isFinite(delayAttr) ? delayAttr : 8000;
    let introSent = false;

    function toggleTyping(isVisible) {
      if (!typingEl) {
        return;
      }
      typingEl.classList.toggle('is-visible', isVisible);
      typingEl.setAttribute('aria-hidden', isVisible ? 'false' : 'true');
    }

    function addMessage(text, author, options) {
      const bubble = document.createElement('div');
      bubble.className = 'nf-chatbot__bubble is-' + author;
      const parts = text ? text.split('\n') : [];
      parts.forEach(function (part) {
        if (!part.trim()) {
          return;
        }
        const paragraph = document.createElement('p');
        paragraph.textContent = part.trim();
        bubble.appendChild(paragraph);
      });
      if (options && options.cta) {
        const link = document.createElement('a');
        link.href = options.cta.url;
        link.className = 'nf-chatbot__cta';
        link.textContent = options.cta.label;
        if (options.cta.external) {
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
        }
        bubble.appendChild(link);
      }
      messagesEl.appendChild(bubble);
      messagesEl.scrollTop = messagesEl.scrollHeight;
      if (author === 'bot' && !widget.classList.contains('is-open')) {
        widget.classList.add('has-unread');
      }
    }

    function sendBotMessage(payload) {
      const data = typeof payload === 'string' ? { text: payload } : payload;
      addMessage(data.text || '', 'bot', { cta: data.cta });
    }

    function matchIntent(message) {
      const normalized = normalizeText(message);
      const found = knowledgeBase.find(function (item) {
        return item.keywords.some(function (keyword) {
          return normalized.indexOf(keyword) !== -1;
        });
      });
      return found ? found.response : null;
    }

    function respondToUser(message, intentOverride) {
      const text = message.trim();
      if (!text) {
        return;
      }
      addMessage(text, 'user');
      input.value = '';
      toggleTyping(true);
      setTimeout(function () {
        const reply =
          (intentOverride && quickReplies[intentOverride]) ||
          matchIntent(text) ||
          fallbackResponse;
        sendBotMessage(reply);
        toggleTyping(false);
      }, 750);
    }

    function runIntro() {
      if (introSent) {
        return;
      }
      introSent = true;
      const introMessages = [
        'Oi! Eu sou a NFit Pulse, assistente virtual da NutremFit.',
        {
          text: 'Respondo em segundos e te levo para o plano certo. Escolha uma op\u00e7\u00e3o r\u00e1pida ou escreva sua pergunta.',
          cta: { label: 'Conhecer o funcionamento', url: '/services' }
        }
      ];
      introMessages.forEach(function (message, index) {
        setTimeout(function () {
          sendBotMessage(message);
        }, index * 1600);
      });
    }

    function openWidget(autoOpen) {
      widget.classList.add('is-open');
      widget.classList.remove('has-unread');
      windowEl.setAttribute('aria-hidden', 'false');
      launcher.setAttribute('aria-expanded', 'true');
      runIntro();
      if (!autoOpen) {
        safeSession.set(storageKey, '1');
      }
    }

    function closeWidget() {
      widget.classList.remove('is-open');
      windowEl.setAttribute('aria-hidden', 'true');
      launcher.setAttribute('aria-expanded', 'false');
    }

    if (!launcher) {
      return;
    }

    launcher.addEventListener('click', function () {
      if (widget.classList.contains('is-open')) {
        closeWidget();
      } else {
        openWidget(false);
      }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        closeWidget();
      });
    }

    if (form && input) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        respondToUser(input.value);
      });
    }

    quickButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        const question = button.getAttribute('data-nf-question') || '';
        const intent = button.getAttribute('data-nf-intent');
        if (!widget.classList.contains('is-open')) {
          openWidget(false);
        }
        respondToUser(question, intent);
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && widget.classList.contains('is-open')) {
        closeWidget();
      }
    });

    const alreadyAutoOpened = safeSession.get(storageKey) === '1';
    if (!alreadyAutoOpened) {
      setTimeout(function () {
        if (!widget.classList.contains('is-open')) {
          openWidget(true);
          safeSession.set(storageKey, '1');
        }
      }, autoDelay);
    }
  });
})();
