function layoutAgenda() {
  document.querySelectorAll('[data-agenda]').forEach((root) => {
    const pad = 14;
    const height = Number(root.getAttribute('data-agenda-height') || 0) + pad;
    root.querySelectorAll('.agenda-hours, .agenda-col').forEach((el) => {
      el.style.height = height + 'px';
    });
    root.querySelectorAll('[data-top]').forEach((el) => {
      el.style.top = (Number(el.getAttribute('data-top') || 0) + pad) + 'px';
      if (el.hasAttribute('data-height')) el.style.height = el.getAttribute('data-height') + 'px';
      if (el.hasAttribute('data-left')) {
        el.style.left = 'calc(' + el.getAttribute('data-left') + '% + 4px)';
        el.style.width = 'calc(' + el.getAttribute('data-width') + '% - 8px)';
        el.style.right = 'auto';
      }
    });
    root.querySelectorAll('.agenda-now').forEach((mark) => mark.remove());
    const open = Number(root.getAttribute('data-agenda-open') || 0);
    const close = Number(root.getAttribute('data-agenda-close') || 0);
    const hour = Number(root.getAttribute('data-agenda-hour') || 64);
    const now = new Date();
    const minutes = now.getHours() * 60 + now.getMinutes();
    if (minutes < open || minutes > close) return;
    const top = ((minutes - open) / 60) * hour + pad;
    const label = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    root.querySelectorAll('.agenda-col.is-today').forEach((col) => {
      const mark = document.createElement('i');
      mark.className = 'agenda-now';
      mark.style.top = top + 'px';
      const text = document.createElement('span');
      text.textContent = label;
      mark.appendChild(text);
      col.appendChild(mark);
    });
  });
}

function bootPage() {
layoutAgenda();
if (window.dxTrackTimer) {
  clearInterval(window.dxTrackTimer);
  window.dxTrackTimer = 0;
}
if (window.dxHookTimer) {
  clearInterval(window.dxHookTimer);
  window.dxHookTimer = 0;
}
if (window.dxWaTimer) {
  clearInterval(window.dxWaTimer);
  window.dxWaTimer = 0;
}
if (window.dxTrackMap) {
  window.dxTrackMap.remove();
  window.dxTrackMap = null;
}
const settings = document.querySelector('[data-settings]');
if (settings) {
  const tabs = [...settings.querySelectorAll('[data-tab]')];
  const form = settings.querySelector('[data-settings-form]');
  const panels = [...settings.querySelectorAll('[data-panel]')];
  const editing = settings.hasAttribute('data-editing');
  const showTab = (id) => {
    if (editing) return;
    tabs.forEach((tab) => {
      const active = tab.dataset.tab === id;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach((panel) => {
      panel.hidden = panel.dataset.panel !== id;
    });
  };
  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      if (editing) {
        window.location.href = '/configuracoes?editar=' + encodeURIComponent(tab.dataset.tab || 'empresa');
        return;
      }
      showTab(tab.dataset.tab);
    });
  });
  const start = settings.getAttribute('data-active-tab') || 'empresa';
  tabs.forEach((tab) => {
    const active = tab.dataset.tab === start;
    tab.classList.toggle('is-active', active);
    tab.setAttribute('aria-selected', active ? 'true' : 'false');
  });
  if (!editing) showTab(start);
  settings.querySelectorAll('[data-hours-row]').forEach((row) => {
    const toggle = row.querySelector('[data-hours-toggle]');
    const times = [...row.querySelectorAll('input[type="time"]')];
    const sync = () => times.forEach((input) => { input.disabled = !toggle.checked; });
    toggle?.addEventListener('change', sync);
    sync();
  });
  form?.addEventListener('submit', () => {
    form.querySelectorAll('input[type="time"]:disabled').forEach((input) => { input.disabled = false; });
  });
  const wa = settings.querySelector('[data-wa]');
  if (wa && !editing) {
    const csrf = wa.querySelector('[name="_csrf"]')?.value || '';
    const label = wa.querySelector('[data-wa-label]');
    const help = wa.querySelector('[data-wa-help]');
    const profile = wa.querySelector('[data-wa-profile]');
    const qr = wa.querySelector('[data-wa-qr]');
    const connect = wa.querySelector('[data-wa-connect]');
    const disconnect = wa.querySelector('[data-wa-disconnect]');
    const receipt = wa.querySelector('[data-wa-receipt]');
    const error = wa.querySelector('[data-wa-error]');
    const setup = wa.querySelector('[data-wa-setup]');
    const twoStart = wa.querySelector('[data-wa-2fa-start]');
    const twoQr = wa.querySelector('[data-wa-2fa-qr]');
    const twoSecret = wa.querySelector('[data-wa-2fa-secret]');
    const twoConfirmField = wa.querySelector('[data-wa-2fa-confirm-field]');
    const twoCode = wa.querySelector('[data-wa-2fa-code]');
    const twoConfirm = wa.querySelector('[data-wa-2fa-confirm]');
    const codeField = wa.querySelector('[data-wa-code-field]');
    const codeInput = wa.querySelector('[data-wa-code]');
    let twoFactor = wa.dataset.wa2fa === '1';
    let armed = false;
    let armTimer = 0;
    const helpFor = (status) => {
      if (!twoFactor) return 'Ative o 2FA. O mesmo código passa a ser obrigatório para entrar na plataforma.';
      if (status === 'conectado') return 'As mensagens saem por este WhatsApp. Excluir a instância tira o aparelho e apaga a instância na API. Confirme duas vezes com o código do 2FA.';
      if (status === 'aguardando') return 'No celular, o aparelho aparece como DRIVEXPERIENCE TESTE. WhatsApp, Aparelhos conectados, Conectar aparelho.';
      if (status === 'hibernado') return 'A sessão está pausada. Informe o código do 2FA para gerar o QR Code de novo.';
      return 'Informe o código do 2FA para gerar o QR Code. O aparelho aparece como DRIVEXPERIENCE TESTE.';
    };
    const resetArm = () => {
      armed = false;
      window.clearTimeout(armTimer);
      if (disconnect) disconnect.textContent = 'Excluir instância';
      if (codeField && wa.dataset.waStatus === 'conectado') codeField.hidden = true;
    };
    const paint = (data) => {
      const status = data.status || wa.dataset.waStatus || 'desconectado';
      if (typeof data.two_factor === 'boolean') {
        twoFactor = data.two_factor;
        wa.dataset.wa2fa = twoFactor ? '1' : '0';
      }
      wa.dataset.waStatus = status;
      if (setup) setup.hidden = twoFactor;
      if (label) label.textContent = data.label || 'Desconectado';
      if (help && !armed) help.textContent = data.message || helpFor(status);
      if (profile) {
        profile.hidden = !data.profile;
        profile.textContent = data.profile || '';
      }
      const showQr = twoFactor && status !== 'conectado' && !!data.qr;
      if (qr) {
        qr.hidden = !showQr;
        if (showQr) {
          if (qr.getAttribute('src') !== data.qr) qr.setAttribute('src', data.qr);
        } else {
          qr.removeAttribute('src');
        }
      }
      const showConnect = twoFactor && status !== 'conectado';
      if (connect) {
        connect.hidden = !showConnect;
        connect.disabled = !showConnect;
      }
      if (codeField) codeField.hidden = !(showConnect || (twoFactor && armed && status !== 'desconectado'));
      if (disconnect) disconnect.hidden = status === 'desconectado';
      if (receipt) receipt.hidden = status !== 'conectado';
      if (status === 'desconectado') resetArm();
      if (error) {
        error.hidden = !data.error;
        error.textContent = data.error || '';
      }
    };
    const post = async (url, extra) => {
      const body = new FormData();
      body.append('_csrf', csrf);
      Object.entries(extra || {}).forEach(([key, value]) => body.append(key, value));
      const response = await fetch(url, { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        if (error) {
          error.hidden = false;
          error.textContent = data.error || 'Não foi possível concluir.';
        }
        return;
      }
      if (error) error.hidden = true;
      paint(data);
    };
    twoStart?.addEventListener('click', async () => {
      twoStart.disabled = true;
      try {
        const body = new FormData();
        body.append('_csrf', csrf);
        const response = await fetch('/configuracoes/whatsapp/2fa', { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          if (error) {
            error.hidden = false;
            error.textContent = data.error || 'Não foi possível iniciar o 2FA.';
          }
        } else {
          if (error) error.hidden = true;
          if (twoQr && data.qr) {
            twoQr.hidden = false;
            twoQr.src = data.qr;
          }
          if (twoSecret) {
            twoSecret.hidden = false;
            twoSecret.textContent = data.secret || '';
          }
          if (twoConfirmField) twoConfirmField.hidden = false;
          if (twoConfirm) twoConfirm.hidden = false;
          twoStart.hidden = true;
          if (help) help.textContent = 'Escaneie no aplicativo autenticador e confirme o código de 6 dígitos.';
        }
      } catch (failure) {
        if (error) {
          error.hidden = false;
          error.textContent = 'Não foi possível iniciar o 2FA.';
        }
      }
      twoStart.disabled = false;
    });
    twoConfirm?.addEventListener('click', async () => {
      twoConfirm.disabled = true;
      try {
        await post('/configuracoes/whatsapp/2fa/ativar', { code: twoCode?.value.trim() || '' });
      } catch (failure) {
        if (error) {
          error.hidden = false;
          error.textContent = 'Não foi possível ativar o 2FA.';
        }
      }
      twoConfirm.disabled = false;
    });
    connect?.addEventListener('click', async () => {
      if (!twoFactor) {
        if (error) {
          error.hidden = false;
          error.textContent = 'Ative o 2FA da plataforma antes de gerar o QR Code.';
        }
        return;
      }
      const code = codeInput?.value.trim() || '';
      if (code.length !== 6) {
        if (error) {
          error.hidden = false;
          error.textContent = 'Informe o código de 6 dígitos do 2FA.';
        }
        return;
      }
      connect.disabled = true;
      try {
        await post('/configuracoes/whatsapp', { code });
      } catch (failure) {
        if (error) {
          error.hidden = false;
          error.textContent = 'Não foi possível gerar o QR Code.';
        }
      }
      connect.disabled = false;
    });
    disconnect?.addEventListener('click', async () => {
      if (!armed) {
        armed = true;
        disconnect.textContent = 'Confirmar exclusão';
        if (codeField) codeField.hidden = false;
        if (help) help.textContent = 'Toque outra vez e informe o código do 2FA. A instância sai do celular e é excluída na API.';
        codeInput?.focus();
        window.clearTimeout(armTimer);
        armTimer = window.setTimeout(resetArm, 20000);
        return;
      }
      const code = codeInput?.value.trim() || '';
      if (code.length !== 6) {
        if (error) {
          error.hidden = false;
          error.textContent = 'Informe o código de 6 dígitos do 2FA.';
        }
        return;
      }
      resetArm();
      disconnect.disabled = true;
      try {
        await post('/configuracoes/whatsapp/desconectar', { confirm: 'sim', again: 'sim', code });
      } catch (failure) {
        if (error) {
          error.hidden = false;
          error.textContent = 'Não foi possível desconectar.';
        }
      }
      disconnect.disabled = false;
    });
    if (window.dxWaTimer) window.clearInterval(window.dxWaTimer);
    window.dxWaTimer = window.setInterval(async () => {
      if (wa.closest('[data-panel]')?.hidden) return;
      try {
        const response = await fetch('/configuracoes/whatsapp', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const data = await response.json().catch(() => null);
        if (response.ok && data) paint(data);
      } catch (error) {
        return;
      }
    }, 4000);
  }
}

const rolePick = document.querySelector('[data-role-pick]');
if (rolePick) {
  const rolePanels = [...document.querySelectorAll('[data-role-panel]')];
  const showRole = () => {
    rolePanels.forEach((panel) => {
      panel.hidden = panel.getAttribute('data-role-panel') !== rolePick.value;
    });
  };
  rolePick.addEventListener('change', showRole);
  showRole();
}

const pageTools = document.querySelector('.page-tools');
const primaryAction = document.querySelector('.content > .toolbar > a.button-link');
if (pageTools && primaryAction) {
  const bar = primaryAction.parentElement;
  pageTools.append(primaryAction);
  if (bar && bar.classList.contains('toolbar') && bar.children.length === 0) {
    bar.remove();
  }
}

document.querySelectorAll('tr[data-href]').forEach((row) => {
  row.addEventListener('click', (event) => {
    if (event.target.closest('a, button, input, label, summary, details')) return;
    window.location.href = row.dataset.href;
  });
});

document.querySelectorAll('[data-bar]').forEach((bar) => {
  const value = Number(bar.getAttribute('data-bar') || 0);
  bar.style.height = Math.max(0, Math.min(100, value)) + '%';
});
document.querySelectorAll('[data-share]').forEach((bar) => {
  const value = Number(bar.getAttribute('data-share') || 0);
  bar.style.width = Math.max(0, Math.min(100, value)) + '%';
});

const wizard = document.querySelector('[data-wizard]');
if (wizard) {
  const steps = [...wizard.querySelectorAll('[data-step]')];
  const dots = [...document.querySelectorAll('[data-step-dot]')];
  const catalog = JSON.parse(wizard.getAttribute('data-catalog') || '{}');
  const money = (cents) => 'R$ ' + (cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const field = (name) => wizard.querySelector('[name="' + name + '"]');
  const checkedMode = (name) => wizard.querySelector('[name="' + name + '"]:checked')?.value || '';
  let current = 0;
  const show = (index) => {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, stepIndex) => { step.hidden = stepIndex !== current; });
    dots.forEach((dot, stepIndex) => dot.classList.toggle('current', stepIndex === current));
    const prev = wizard.querySelector('[data-prev]');
    const next = wizard.querySelector('[data-next]');
    const confirm = wizard.querySelector('[data-confirm-reservation]');
    if (prev) prev.hidden = current === 0;
    if (next) next.hidden = current === steps.length - 1;
    if (confirm) confirm.hidden = current !== steps.length - 1;
    if (current === steps.length - 1) paintReview();
    syncFinish();
  };
  const setError = (index, message) => {
    const slot = steps[index]?.querySelector('[data-step-error]');
    if (slot) slot.textContent = message || '';
  };
  const selectedModalities = () => [...wizard.querySelectorAll('[name="modality_id[]"]:checked')].map((input) => input.value);
  const lineFor = (id) => {
    const type = catalog.types?.[id];
    if (type !== 'experiencia') return catalog.fixed?.[id] || null;
    const vehicleId = field('vehicle_id')?.value || '';
    return catalog.prices?.[vehicleId]?.[id] || null;
  };
  const paintPrices = () => {
    let total = 0;
    let missing = false;
    wizard.querySelectorAll('[data-modality]').forEach((row) => {
      const id = row.getAttribute('data-modality');
      const price = lineFor(id);
      const slot = row.querySelector('[data-modality-price]');
      if (slot) slot.textContent = price ? price.label : (catalog.types?.[id] === 'experiencia' ? 'não oferecido' : '');
      if (row.querySelector('input')?.checked) {
        if (!price) missing = true;
        else total += Number(price.cents) || 0;
      }
    });
    const duration = selectedModalities().reduce((max, id) => catalog.types?.[id] === 'experiencia' ? Math.max(max, Number(catalog.durations?.[id]) || 0) : max, 0);
    const durationField = field('duration_minutes');
    if (durationField) durationField.value = String(duration);
    wizard.querySelectorAll('[data-sale-total]').forEach((node) => { node.textContent = money(total); });
    return { total, missing, duration };
  };
  const titleCase = (value) => {
    const particles = new Set(['da', 'de', 'do', 'das', 'dos', 'e']);
    return value.trim().replace(/\s+/g, ' ').toLocaleLowerCase('pt-BR').split(' ').map((part, index) => {
      if (!part || (index > 0 && particles.has(part))) return part;
      return part.charAt(0).toLocaleUpperCase('pt-BR') + part.slice(1);
    }).join(' ');
  };
  const maskCpf = (input) => {
    const digits = input.value.replace(/\D/g, '').slice(0, 11);
    let out = digits;
    if (digits.length > 9) out = digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9);
    else if (digits.length > 6) out = digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6);
    else if (digits.length > 3) out = digits.slice(0, 3) + '.' + digits.slice(3);
    input.value = out;
  };
  const phoneCode = (input) => input.parentElement?.querySelector('select')?.value || '55';
  const maskPhone = (input) => {
    if (phoneCode(input) !== '55') return;
    const digits = input.value.replace(/\D/g, '').slice(0, 11);
    let out = digits;
    if (digits.length > 2) out = '(' + digits.slice(0, 2) + ')';
    if (digits.length > 2) out += ' ' + digits.slice(2, 3);
    if (digits.length > 3) out += ' ' + digits.slice(3, 7);
    if (digits.length > 7) out += ' - ' + digits.slice(7, 11);
    input.value = out;
  };
  const syncPhone = (input) => {
    if (phoneCode(input) === '55') {
      input.placeholder = '(00) 0 0000 - 0000';
      maskPhone(input);
      return;
    }
    input.placeholder = '';
  };
  const syncClient = () => {
    const mode = checkedMode('client_mode');
    wizard.querySelectorAll('[data-client-panel]').forEach((panel) => { panel.hidden = panel.getAttribute('data-client-panel') !== mode; });
  };
  const syncPartner = () => {
    const mode = checkedMode('partner_mode') || 'none';
    wizard.querySelectorAll('[data-partner-panel]').forEach((panel) => { panel.hidden = panel.getAttribute('data-partner-panel') !== mode; });
  };
  const syncOrigin = () => {
    const option = field('origin_id')?.selectedOptions?.[0];
    const box = wizard.querySelector('[data-platform-fields]');
    if (box) box.hidden = !option || option.getAttribute('data-platform') !== '1';
  };
  const syncPayment = () => {
    const plan = field('payment_plan')?.value || '';
    const extra = wizard.querySelector('[data-pay-extra]');
    const amount = wizard.querySelector('[data-pay-amount]');
    const note = wizard.querySelector('[data-convenio-note]');
    if (extra) extra.hidden = plan !== 'total' && plan !== 'slip';
    if (amount) amount.hidden = plan !== 'slip';
    if (note) note.hidden = plan !== 'convenio';
  };
  const discountCents = (total) => {
    const kind = field('discount_kind')?.value || 'reais';
    const raw = (field('discount')?.value || '').trim().replace('%', '');
    if (!raw) return 0;
    const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw;
    const amount = Number(normalized);
    if (!Number.isFinite(amount) || amount < 0) return null;
    if (kind === 'percent') {
      if (amount > 100) return null;
      return Math.round(total * amount / 100);
    }
    return Math.round(amount * 100);
  };
  const syncDiscount = () => {
    const kind = field('discount_kind')?.value || 'reais';
    const input = field('discount');
    if (input) input.placeholder = kind === 'percent' ? '0' : '0,00';
    const note = wizard.querySelector('[data-discount-note]');
    if (!note) return;
    const { total } = paintPrices();
    const off = discountCents(total);
    if (off === null) {
      note.textContent = kind === 'percent' ? 'Use uma porcentagem de 0 a 100.' : 'Use um valor em reais, como 50,00.';
      return;
    }
    note.textContent = (kind === 'percent' ? 'Porcentagem sobre a tabela' : 'Desconto em reais') + ': ' + money(off) + '. Valor com desconto: ' + money(Math.max(0, total - off)) + '.';
  };
  const paintReview = () => {
    const box = wizard.querySelector('[data-review]');
    if (!box) return;
    const { total } = paintPrices();
    const clientMode = checkedMode('client_mode');
    const client = clientMode === 'existing'
      ? (field('client_id')?.selectedOptions?.[0]?.textContent || '')
      : (field('client_name')?.value || '');
    const lines = selectedModalities().map((id) => {
      const price = lineFor(id);
      return (catalog.names?.[id] || 'Item') + ' · ' + (price ? price.label : 'não oferecido');
    });
    const plan = field('payment_plan')?.selectedOptions?.[0]?.textContent || '';
    const partnerMode = checkedMode('partner_mode');
    const partner = partnerMode === 'existing'
      ? (field('partner_id')?.selectedOptions?.[0]?.textContent || '')
      : (partnerMode === 'new' ? (field('partner_name')?.value || '') : '');
    const rows = [
      ['Cliente', client],
      ['Origem', field('origin_id')?.selectedOptions?.[0]?.textContent || ''],
      ['Veículo', field('vehicle_id')?.selectedOptions?.[0]?.textContent || ''],
      ['Experiência', lines.join(' / ') || 'Nenhuma'],
      ['Quando', [field('scheduled_date')?.value, field('start_time')?.value, (field('duration_minutes')?.value || '0') + ' min'].filter(Boolean).join(' · ')],
      ['Tabela', money(total)],
      ['Desconto', (() => {
        const off = discountCents(total);
        if (off === null) return 'Inválido';
        const kind = field('discount_kind')?.value === 'percent' ? (field('discount')?.value || '0') + '% · ' : '';
        return kind + money(off);
      })()],
      ['Valor final', (() => {
        const off = discountCents(total);
        return off === null ? '—' : money(Math.max(0, total - off));
      })()],
      ['Pagamento', plan]
    ];
    if (partner) rows.splice(3, 0, ['Parceria', partner]);
    box.replaceChildren();
    rows.forEach(([label, value]) => {
      const row = document.createElement('div');
      const term = document.createElement('dt');
      const detail = document.createElement('dd');
      term.textContent = label;
      detail.textContent = value || '—';
      row.append(term, detail);
      box.append(row);
    });
  };
  const validate = (index) => {
    const modes = selectedModalities();
    const needsDrive = modes.some((id) => catalog.types?.[id] === 'experiencia');
    if (index === 0) {
      if (checkedMode('client_mode') === 'existing') {
        if (!field('client_id')?.value) return 'Selecione o cliente cadastrado.';
        return '';
      }
      if ((field('client_name')?.value || '').trim().length < 3) return 'Informe o nome completo.';
      const cpf = (field('client_cpf')?.value || '').replace(/\D/g, '');
      if (cpf && cpf.length !== 11) return 'O CPF precisa ficar no formato 000.000.000-00.';
      const cnh = field('client_cnh');
      if (!(cnh?.files && cnh.files.length) && !field('cnh_file_id')?.value) return 'Envie a CNH de quem vai fazer a reserva.';
      return '';
    }
    if (index === 1) {
      if (!field('origin_id')?.value) return 'Selecione a origem da venda.';
      const option = field('origin_id').selectedOptions[0];
      if (option?.getAttribute('data-platform') === '1' && !field('platform_id')?.value) return 'Informe a plataforma.';
      if (checkedMode('partner_mode') === 'existing' && !field('partner_id')?.value) return 'Selecione o parceiro.';
      if (checkedMode('partner_mode') === 'new') {
        if ((field('partner_name')?.value || '').trim().length < 3) return 'Informe o nome do parceiro.';
        if (!field('partner_commission')?.value.trim()) return 'Informe a comissão do parceiro.';
      }
      return '';
    }
    if (index === 2 && needsDrive && !field('vehicle_id')?.value) return 'Selecione o veículo da experiência.';
    if (index === 3) {
      if (modes.length === 0) return 'Selecione ao menos uma experiência ou um pacote.';
      if (needsDrive && !field('vehicle_id')?.value) return 'Selecione o veículo da experiência.';
      if (paintPrices().missing) return 'Há item sem preço para o veículo escolhido.';
      return '';
    }
    if (index === 4 && needsDrive) {
      if (!field('scheduled_date')?.value || !field('start_time')?.value) return 'Informe data e horário da experiência.';
    }
    if (index === 5) {
      if (discountCents(paintPrices().total) === null) return 'Informe o desconto em reais ou uma porcentagem de 0 a 100.';
      const plan = field('payment_plan')?.value || '';
      if (!plan) return 'Selecione pagamento total, slip ou convênio.';
      if ((plan === 'total' || plan === 'slip') && !field('payment_method_id')?.value) return 'Selecione a forma de pagamento.';
      if (plan === 'slip' && !(field('payment_amount')?.value || '').trim()) return 'Informe o valor pago agora.';
    }
    return '';
  };
  const stepNames = ['Cliente', 'Origem', 'Veículo', 'Experiência', 'Agenda', 'Valores'];
  const gaps = () => stepNames.filter((name, index) => validate(index) !== '');
  const syncFinish = () => {
    const confirm = wizard.querySelector('[data-confirm-reservation]');
    const hint = wizard.querySelector('[data-finish-hint]');
    const pending = gaps();
    const ready = pending.length === 0;
    const last = current === steps.length - 1;
    if (confirm) confirm.disabled = !ready;
    if (hint) {
      hint.hidden = last;
      hint.textContent = ready ? 'Tudo obrigatório está preenchido.' : 'Continuar libera depois de: ' + pending.join(', ') + '.';
    }
    dots.forEach((dot, index) => {
      dot.classList.toggle('is-done', index < stepNames.length && validate(index) === '');
    });
  };
  wizard.addEventListener('click', (event) => {
    if (event.target.closest('[data-next]')) {
      event.preventDefault();
      const message = validate(current);
      setError(current, message);
      if (!message) show(current + 1);
    }
    if (event.target.closest('[data-prev]')) {
      event.preventDefault();
      show(current - 1);
    }
  });
  wizard.addEventListener('submit', (event) => {
    const pending = gaps();
    if (pending.length === 0 && current === steps.length - 1) return;
    event.preventDefault();
    if (pending.length === 0) return;
    const index = stepNames.findIndex((name, stepIndex) => validate(stepIndex) !== '');
    show(index);
    setError(index, validate(index));
  });
  wizard.addEventListener('change', (event) => {
    if (event.target.name === 'client_mode') syncClient();
    if (event.target.name === 'partner_mode') syncPartner();
    if (event.target.name === 'origin_id') syncOrigin();
    if (event.target.name === 'payment_plan') syncPayment();
    if (event.target.name === 'discount_kind') syncDiscount();
    if (event.target.name === 'vehicle_id' || event.target.name === 'modality_id[]') syncDiscount();
    const dial = event.target.closest('.dial');
    if (dial && event.target.tagName === 'SELECT') {
      const input = dial.querySelector('[data-phone]');
      if (!input) return;
      if (event.target.value === '55') syncPhone(input);
      else {
        input.value = '';
        input.placeholder = '';
      }
    }
    syncFinish();
  });
  wizard.addEventListener('blur', (event) => {
    if (event.target.matches('[data-person-name]')) event.target.value = titleCase(event.target.value);
  }, true);
  wizard.addEventListener('input', (event) => {
    if (event.target.matches('[data-cpf]')) maskCpf(event.target);
    if (event.target.matches('[data-phone]')) maskPhone(event.target);
    if (event.target.name === 'discount') syncDiscount();
    syncFinish();
  });
  syncClient();
  syncPartner();
  syncOrigin();
  syncPayment();
  syncDiscount();
  wizard.querySelectorAll('[data-phone]').forEach(syncPhone);
  paintPrices();
  show(0);
}

document.querySelectorAll('[data-fleet]').forEach((fleet) => {
  const buttons = [...fleet.querySelectorAll('[data-vehicle-view]')];
  const panels = [...fleet.querySelectorAll('[data-vehicle-panel]')];
  const show = (id) => {
    buttons.forEach((button) => {
      const active = button.dataset.vehicleView === id;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach((panel) => { panel.hidden = panel.dataset.vehiclePanel !== id; });
  };
  buttons.forEach((button) => button.addEventListener('click', () => show(button.dataset.vehicleView)));
  show('grade');
});

const availability = document.querySelector('[data-availability]');
if (availability) {
  availability.addEventListener('click', async () => {
    const params = new URLSearchParams({
      vehicle_id: document.querySelector('[name="vehicle_id"]').value,
      date: document.querySelector('[name="scheduled_date"]').value,
      start: document.querySelector('[name="start_time"]').value,
      duration: document.querySelector('[name="duration_minutes"]').value
    });
    const response = await fetch('/agenda/disponibilidade?' + params.toString());
    const data = await response.json();
    document.querySelector('[data-availability-result]').textContent = data.message || (data.ok ? 'Horário disponível.' : 'Horário indisponível.');
  });
}

const quoteForm = document.querySelector('[data-quote]');
if (quoteForm) {
  const prices = JSON.parse(quoteForm.getAttribute('data-prices') || '{}');
  const vehicle = quoteForm.querySelector('[name="vehicle_id"]');
  const paint = () => {
    const map = prices[vehicle?.value] || {};
    quoteForm.querySelectorAll('[data-modality]').forEach((row) => {
      const slot = row.querySelector('[data-quote-price]');
      if (!slot || slot.hasAttribute('data-fixed')) return;
      slot.textContent = map[row.getAttribute('data-modality')] || '';
    });
  };
  vehicle?.addEventListener('change', paint);
  paint();
}

document.querySelectorAll('[data-edit-platform]').forEach((button) => {
  button.addEventListener('click', () => {
    const root = document.querySelector('[data-drawers]');
    if (!root) return;
    const id = button.getAttribute('data-edit-platform');
    root.hidden = false;
    root.querySelectorAll('[data-drawer]').forEach((form) => {
      form.hidden = form.getAttribute('data-drawer') !== id;
    });
    const title = document.getElementById('drawer-title-' + id);
    const dialog = root.querySelector('.drawer');
    if (title && dialog) dialog.setAttribute('aria-labelledby', title.id);
  });
});
document.querySelectorAll('[data-drawer-close]').forEach((control) => {
  control.addEventListener('click', () => {
    const root = document.querySelector('[data-drawers]');
    if (root) root.hidden = true;
  });
});
document.querySelectorAll('[data-doc-export]').forEach((root) => {
  const choices = [...root.querySelectorAll('[data-doc]')];
  const download = root.querySelector('[data-doc-download]');
  const formats = root.querySelector('[data-doc-formats]');
  const hint = root.querySelector('[data-doc-hint]');
  const preview = root.querySelector('[data-doc-preview]');
  const frame = root.querySelector('[data-doc-frame]');
  const title = root.querySelector('[data-doc-preview-title]');
  const formatLabel = root.querySelector('[data-doc-preview-format]');
  const fileLink = root.querySelector('[data-doc-preview-download]');
  const issueForm = root.querySelector('[data-doc-preview-issue]');
  const reservation = root.getAttribute('data-reservation');
  let selected = '';
  let armed = '';
  const choice = () => choices.find((item) => item.getAttribute('data-doc') === selected);
  const openPreview = (format) => {
    const current = choice();
    if (!current || !preview || !frame) return;
    const label = current.textContent.trim();
    if (title) title.textContent = label;
    preview.classList.toggle('is-sheet', format === 'csv');
    if (formatLabel) formatLabel.textContent = format === 'csv' ? 'Planilha CSV em paisagem · clique em Baixar para salvar o arquivo' : 'PDF · confira o documento antes de baixar';
    frame.src = '/reservas/' + reservation + '/previa?tipo=' + encodeURIComponent(selected) + '&formato=' + format;
    if (fileLink) {
      fileLink.hidden = false;
      fileLink.href = format === 'csv'
        ? '/reservas/' + reservation + '/csv?tipo=' + encodeURIComponent(selected)
        : '/reservas/' + reservation + '/pdf?tipo=' + encodeURIComponent(selected);
      fileLink.textContent = format === 'csv' ? 'Baixar CSV' : 'Baixar PDF';
    }
    if (issueForm) issueForm.hidden = true;
    if (typeof preview.showModal === 'function') preview.showModal();
    else preview.hidden = false;
  };
  choices.forEach((item) => {
    item.addEventListener('click', () => {
      selected = item.getAttribute('data-doc') || '';
      armed = '';
      choices.forEach((option) => {
        const on = option === item;
        option.classList.toggle('is-selected', on);
        option.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      root.querySelectorAll('[data-format]').forEach((button) => button.classList.remove('is-selected'));
      if (download) download.disabled = selected === '';
      if (formats) formats.hidden = true;
      if (hint) hint.textContent = 'Selecionado: ' + item.textContent.trim() + '. Clique em Baixar.';
    });
  });
  download?.addEventListener('click', () => {
    if (!selected || !formats) return;
    formats.hidden = false;
    if (hint) hint.textContent = 'Documento selecionado. Clique duas vezes em CSV ou PDF para abrir a pré-visualização.';
  });
  root.querySelectorAll('[data-format]').forEach((button) => {
    button.addEventListener('click', () => {
      const format = button.getAttribute('data-format') || '';
      root.querySelectorAll('[data-format]').forEach((item) => item.classList.toggle('is-selected', item === button));
      if (armed === format) {
        openPreview(format);
        return;
      }
      armed = format;
      if (hint) hint.textContent = 'Formato ' + format.toUpperCase() + ' selecionado. Clique novamente para ver a pré-visualização.';
    });
    button.addEventListener('dblclick', (event) => {
      event.preventDefault();
      armed = button.getAttribute('data-format') || '';
      openPreview(armed);
    });
  });
  root.querySelectorAll('[data-doc-preview-close]').forEach((button) => {
    button.addEventListener('click', () => {
      if (frame) frame.removeAttribute('src');
      preview?.classList.remove('is-sheet');
      if (typeof preview?.close === 'function') preview.close();
    });
  });
});
document.querySelectorAll('[data-photo-zoom]').forEach((zoom) => {
  const img = zoom.querySelector('img');
  const close = () => {
    zoom.hidden = true;
    if (img) img.removeAttribute('src');
  };
  zoom.parentElement?.querySelectorAll('[data-photo-open]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!img) return;
      img.src = button.getAttribute('data-photo-open') || '';
      img.alt = button.querySelector('img')?.alt || '';
      zoom.hidden = false;
    });
  });
  zoom.addEventListener('click', (event) => {
    if (event.target === zoom || event.target.closest('[data-photo-close]')) close();
  });
});
if (!document.documentElement.dataset.photoZoomBound) {
  document.documentElement.dataset.photoZoomBound = '1';
  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[data-photo-zoom]').forEach((zoom) => {
      if (zoom.hidden) return;
      zoom.hidden = true;
      zoom.querySelector('img')?.removeAttribute('src');
    });
  });
}
document.querySelectorAll('[data-photo-delete]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm('Excluir esta foto?')) {
      event.preventDefault();
      return;
    }
    if (!window.confirm('Confirme outra vez. A foto será apagada e não poderá ser recuperada.')) {
      event.preventDefault();
    }
  });
});
const track = document.querySelector('[data-track]');
if (track) bootTrack(track);
const hook = document.querySelector('[data-hook]');
if (hook) bootHook(hook);
document.querySelectorAll('[data-copy]').forEach((button) => {
  button.addEventListener('click', async () => {
    const node = document.querySelector(button.getAttribute('data-copy') || '');
    const text = (node?.textContent || '').trim();
    try {
      await navigator.clipboard.writeText(text);
      button.textContent = 'Copiado';
    } catch (error) {
      button.textContent = 'Copie o link';
    }
  });
});
document.querySelectorAll('.file-field input[type="file"]').forEach((input) => {
  const pick = input.parentElement.querySelector('.file-pick');
  if (pick) pick.addEventListener('click', () => input.click());
  input.addEventListener('change', () => {
    const slot = input.parentElement.querySelector('[data-file-name]');
    if (slot) slot.textContent = input.files && input.files[0] ? input.files[0].name : 'Nenhum arquivo escolhido';
  });
});
}

const trackLabels = { movendo: 'Em movimento', parado: 'Parado', sem_sinal: 'Sem sinal', sem_tag: 'Sem tag' };

function ensureLeaflet() {
  if (window.L) return Promise.resolve();
  if (!window.dxLeaflet) {
    window.dxLeaflet = new Promise((resolve, reject) => {
      if (!document.querySelector('link[data-leaflet]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/assets/leaflet/leaflet.css';
        link.setAttribute('data-leaflet', '1');
        document.head.appendChild(link);
      }
      const script = document.createElement('script');
      script.src = '/assets/leaflet/leaflet.js';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('mapa'));
      document.head.appendChild(script);
    });
  }
  return window.dxLeaflet;
}

function bootTrack(root) {
  const mapNode = root.querySelector('[data-track-map]');
  const list = root.querySelector('[data-track-list]');
  const empty = root.querySelector('[data-track-empty]');
  const markers = new Map();
  const lines = new Map();
  let fitted = false;
  const statusText = (item) => {
    const label = trackLabels[item.state] || 'Sem sinal';
    if (item.state === 'movendo' && item.speed !== null && item.speed !== undefined) {
      return label + ' · ' + Math.round(item.speed) + ' km/h';
    }
    return label;
  };
  const paintList = (items) => {
    const keys = new Set(items.map((item) => item.id ? 't' + item.id : 'v' + item.vehicle_id));
    list.querySelectorAll('[data-track-key]').forEach((row) => {
      if (!keys.has(row.getAttribute('data-track-key'))) row.remove();
    });
    items.forEach((item) => {
      const key = item.id ? 't' + item.id : 'v' + item.vehicle_id;
      let row = list.querySelector('[data-track-key="' + key + '"]');
      if (!row) {
        row = document.createElement('li');
        row.className = 'track-row';
        row.setAttribute('data-track-key', key);
        row.innerHTML = '<div><strong></strong><span class="muted"></span></div><span class="badge" data-track-state></span>';
        list.appendChild(row);
      }
      row.querySelector('strong').textContent = item.vehicle || item.name || 'Tag';
      row.querySelector('.muted').textContent = item.tag ? (item.name || 'Tag') + ' · ' + item.tag : 'Nenhuma tag ligada';
      const badge = row.querySelector('[data-track-state]');
      badge.className = 'badge st-' + item.state;
      badge.textContent = statusText(item);
    });
    if (empty) empty.hidden = items.some((item) => item.lat !== null && item.lng !== null);
  };
  const paintMap = (items) => {
    const located = items.filter((item) => item.lat !== null && item.lng !== null);
    const seen = new Set(located.map((item) => String(item.id)));
    markers.forEach((marker, key) => {
      if (seen.has(key)) return;
      window.dxTrackMap.removeLayer(marker);
      markers.delete(key);
      const line = lines.get(key);
      if (line) {
        window.dxTrackMap.removeLayer(line);
        lines.delete(key);
      }
    });
    located.forEach((item) => {
      const key = String(item.id);
      const icon = window.L.divIcon({
        className: 'track-pin is-' + item.state,
        html: '<i></i>',
        iconSize: [18, 18],
        iconAnchor: [9, 9]
      });
      let marker = markers.get(key);
      if (!marker) {
        marker = window.L.marker([item.lat, item.lng], { icon }).addTo(window.dxTrackMap);
        markers.set(key, marker);
      } else {
        marker.setLatLng([item.lat, item.lng]);
        marker.setIcon(icon);
      }
      const arrow = marker.getElement()?.querySelector('i');
      if (arrow && item.heading !== null && item.heading !== undefined) {
        arrow.style.transform = 'rotate(' + item.heading + 'deg)';
      }
      const speed = item.speed === null || item.speed === undefined ? '' : ' · ' + Math.round(item.speed) + ' km/h';
      marker.bindPopup((item.vehicle || 'Tag') + speed);
      const trail = Array.isArray(item.trail) ? item.trail : [];
      let line = lines.get(key);
      if (trail.length > 1) {
        if (!line) {
          line = window.L.polyline(trail, { color: '#0F5FA6', weight: 3, opacity: 0.85 }).addTo(window.dxTrackMap);
          lines.set(key, line);
        } else {
          line.setLatLngs(trail);
        }
      }
    });
    if (!fitted && located.length) {
      const bounds = window.L.latLngBounds(located.map((item) => [item.lat, item.lng]));
      window.dxTrackMap.fitBounds(bounds.pad(0.4), { maxZoom: 16 });
      fitted = true;
    }
  };
  const pull = async () => {
    const response = await fetch(root.getAttribute('data-feed'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const data = await response.json();
    const items = data.items || [];
    paintList(items);
    if (window.dxTrackMap) paintMap(items);
  };
  ensureLeaflet().then(() => {
    if (!mapNode || !mapNode.isConnected) return;
    const mapboxToken = (root.getAttribute('data-mapbox-token') || '').trim();
    if (!mapboxToken) {
      if (empty) {
        empty.hidden = false;
        empty.textContent = 'MAPBOX_TOKEN não configurado.';
      }
      return;
    }
    window.dxTrackMap = window.L.map(mapNode, { scrollWheelZoom: true }).setView([-26.9906, -48.6348], 13);
    window.L.tileLayer('https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/{z}/{x}/{y}?access_token=' + encodeURIComponent(mapboxToken), {
      tileSize: 512,
      zoomOffset: -1,
      maxZoom: 20,
      attribution: '&copy; Mapbox &copy; OpenStreetMap'
    }).addTo(window.dxTrackMap);
    pull();
    window.dxTrackTimer = setInterval(pull, 4000);
  }).catch(() => {
    if (empty) {
      empty.hidden = false;
      empty.textContent = 'O mapa não carregou.';
    }
  });
}

function bootHook(root) {
  const list = root.querySelector('[data-hook-tests]');
  const empty = root.querySelector('[data-hook-empty]');
  const selects = [...root.querySelectorAll('select[data-current]')];
  let samples = [];
  let selected = 0;
  const fill = (fields) => {
    selects.forEach((select) => {
      const current = select.value || select.getAttribute('data-current') || '';
      select.replaceChildren();
      const blank = document.createElement('option');
      blank.value = '';
      blank.textContent = 'Não usar';
      select.append(blank);
      const paths = Object.keys(fields);
      if (current && !Object.prototype.hasOwnProperty.call(fields, current)) paths.unshift(current);
      paths.forEach((path) => {
        const option = document.createElement('option');
        option.value = path;
        const value = String(fields[path] || '').slice(0, 48);
        option.textContent = value ? path + ' · ' + value : path;
        if (path === current) option.selected = true;
        select.append(option);
      });
    });
  };
  const paint = () => {
    if (!list) return;
    list.replaceChildren();
    if (empty) empty.hidden = samples.length > 0;
    if (!samples.length) return;
    if (!samples.some((sample) => sample.id === selected)) selected = samples[0].id;
    samples.forEach((sample) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      if (sample.id === selected) button.className = 'is-selected';
      const title = document.createElement('strong');
      title.textContent = sample.at || '';
      const meta = document.createElement('span');
      meta.textContent = (sample.host || 'Origem não informada') + ' · ' + Object.keys(sample.fields || {}).length + ' campos';
      button.append(title, meta);
      button.addEventListener('click', () => {
        selected = sample.id;
        selects.forEach((select) => select.setAttribute('data-current', select.value));
        paint();
      });
      item.append(button);
      list.append(item);
    });
    const current = samples.find((sample) => sample.id === selected);
    const sampleInput = root.querySelector('[data-sample-id]');
    const preview = root.querySelector('[data-hook-preview]');
    if (sampleInput && current) sampleInput.value = String(current.id);
    if (preview) {
      const text = current && current.preview ? current.preview.text || '' : '';
      preview.hidden = text === '';
      preview.textContent = text;
      preview.classList.toggle('erro', !!(current && current.preview && current.preview.status === 'error'));
      preview.classList.toggle('aviso', !(current && current.preview && current.preview.status === 'error'));
    }
    if (current) fill(current.fields || {});
  };
  const pull = async () => {
    if (!root.isConnected) return;
    const response = await fetch(root.getAttribute('data-tests'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!response.ok || !root.isConnected) return;
    const data = await response.json();
    const next = data.samples || [];
    const signature = next.map((sample) => sample.id).join(',');
    const previous = samples.map((sample) => sample.id).join(',');
    samples = next;
    if (signature !== previous) paint();
  };
  pull();
  if (root.getAttribute('data-testing') === '1') window.dxHookTimer = setInterval(pull, 3000);
}

function bootMessage() {
  const layer = document.querySelector('[data-message]');
  const form = layer?.querySelector('[data-message-form]');
  if (!layer || !form || layer.dataset.bound) return;
  layer.dataset.bound = '1';
  const opening = form.querySelector('[data-message-opening]');
  const closing = form.querySelector('[data-message-closing]');
  const vars = form.querySelector('[data-message-vars]');
  const preview = form.querySelector('[data-message-preview]');
  const note = form.querySelector('[data-message-note]');
  const status = form.querySelector('[data-message-status]');
  const send = form.querySelector('[data-message-send]');
  const buttons = form.querySelector('[data-message-buttons]');
  const toggle = form.querySelector('[data-message-buttons-toggle]');
  let pack = null;
  let focused = opening;
  const apply = (source) => {
    let text = source;
    (pack?.vars || []).forEach((item) => { text = text.split(item.token).join(item.value || ''); });
    return text;
  };
  const paint = () => {
    const parts = [apply(opening.value).trim(), apply(closing.value).trim()].filter(Boolean);
    let text = parts.join('\n\n');
    if (toggle.checked) {
      const lines = [...buttons.querySelectorAll('.message-button-row')].map((row) => {
        const label = row.querySelector('[data-button-label]').value.trim();
        if (!label) return '';
        const kind = row.querySelector('[data-button-kind]').value;
        const value = row.querySelector('[data-button-value]').value.trim();
        return kind === 'link' ? label + ' → ' + value : label;
      }).filter(Boolean);
      if (lines.length) text += (text ? '\n\n' : '') + lines.join('\n');
    }
    preview.textContent = text;
  };
  const insert = (token) => {
    const field = focused || opening;
    const start = field.selectionStart ?? field.value.length;
    const end = field.selectionEnd ?? start;
    field.value = field.value.slice(0, start) + token + field.value.slice(end);
    const caret = start + token.length;
    field.focus();
    field.setSelectionRange(caret, caret);
    paint();
  };
  const close = () => { layer.hidden = true; };
  [opening, closing].forEach((field) => {
    field.addEventListener('focus', () => { focused = field; });
    field.addEventListener('input', paint);
  });
  buttons.addEventListener('input', paint);
  toggle.addEventListener('change', () => {
    buttons.hidden = !toggle.checked;
    paint();
  });
  form.querySelectorAll('[data-message-close]').forEach((button) => button.addEventListener('click', close));
  layer.querySelector('.message-backdrop')?.addEventListener('click', close);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !layer.hidden) close();
  });
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-whatsapp]');
    if (!opener) return;
    event.preventDefault();
    opener.closest('details')?.removeAttribute('open');
    pack = JSON.parse(opener.getAttribute('data-whatsapp') || '{}');
    form.querySelector('[data-message-who]').textContent = [pack.name, pack.phone].filter(Boolean).join(' · ');
    note.textContent = pack.configured
      ? 'A mensagem sai pelo WhatsApp da conexão configurada no servidor.'
      : 'A conexão de WhatsApp não está configurada. Nenhuma mensagem é enviada.';
    opening.value = pack.draft || '';
    closing.value = '';
    toggle.checked = false;
    buttons.hidden = true;
    buttons.querySelectorAll('input').forEach((input) => { input.value = ''; });
    buttons.querySelectorAll('select').forEach((select) => { select.value = 'resposta'; });
    status.hidden = true;
    status.textContent = '';
    send.disabled = !pack.configured;
    vars.replaceChildren();
    (pack.vars || []).forEach((item) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'var-chip';
      chip.textContent = item.label;
      chip.addEventListener('click', () => insert(item.token));
      vars.append(chip);
    });
    focused = opening;
    paint();
    layer.hidden = false;
    opening.focus();
  });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!pack?.id || send.disabled) return;
    status.hidden = true;
    send.disabled = true;
    const body = new FormData(form);
    const rows = toggle.checked ? [...buttons.querySelectorAll('.message-button-row')].map((row) => ({
      label: row.querySelector('[data-button-label]').value.trim(),
      kind: row.querySelector('[data-button-kind]').value,
      value: row.querySelector('[data-button-value]').value.trim(),
    })).filter((row) => row.label) : [];
    body.append('buttons', JSON.stringify(rows));
    try {
      const response = await fetch('/reservas/' + pack.id + '/whatsapp', { method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        status.hidden = false;
        status.textContent = data.error || 'Não foi possível enviar a mensagem.';
        send.disabled = !pack.configured;
        return;
      }
      close();
      const main = document.querySelector('main.content');
      const flash = document.createElement('div');
      flash.className = 'flash ok';
      flash.textContent = 'Mensagem enviada.';
      main?.prepend(flash);
    } catch (error) {
      status.hidden = false;
      status.textContent = 'Não foi possível enviar a mensagem.';
      send.disabled = !pack.configured;
    }
  });
  bootPrefill();
}

function bootPrefill() {
  const digits = (value, max) => value.replace(/\D/g, '').slice(0, max);
  const masks = {
    cpf(input) {
      const raw = digits(input.value, 11);
      let out = raw;
      if (raw.length > 9) out = raw.slice(0, 3) + '.' + raw.slice(3, 6) + '.' + raw.slice(6, 9) + '-' + raw.slice(9);
      else if (raw.length > 6) out = raw.slice(0, 3) + '.' + raw.slice(3, 6) + '.' + raw.slice(6);
      else if (raw.length > 3) out = raw.slice(0, 3) + '.' + raw.slice(3);
      input.value = out;
    },
    cnpj(input) {
      const raw = digits(input.value, 14);
      let out = raw;
      if (raw.length > 12) out = raw.slice(0, 2) + '.' + raw.slice(2, 5) + '.' + raw.slice(5, 8) + '/' + raw.slice(8, 12) + '-' + raw.slice(12);
      else if (raw.length > 8) out = raw.slice(0, 2) + '.' + raw.slice(2, 5) + '.' + raw.slice(5, 8) + '/' + raw.slice(8);
      else if (raw.length > 5) out = raw.slice(0, 2) + '.' + raw.slice(2, 5) + '.' + raw.slice(5);
      else if (raw.length > 2) out = raw.slice(0, 2) + '.' + raw.slice(2);
      input.value = out;
    },
    phone(input) {
      if (input.value.includes('+')) return;
      const raw = digits(input.value, 11);
      if (raw.length === 0) {
        input.value = '';
        return;
      }
      let out = '(' + raw.slice(0, 2);
      if (raw.length > 2) out += ') ' + raw.slice(2, 3);
      if (raw.length > 3) out += ' ' + raw.slice(3, 7);
      if (raw.length > 7) out += ' - ' + raw.slice(7, 11);
      input.value = out;
    },
    cep(input) {
      const raw = digits(input.value, 8);
      input.value = raw.length > 5 ? raw.slice(0, 5) + '-' + raw.slice(5) : raw;
    },
    uf(input) {
      input.value = input.value.replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
    },
    plate(input) {
      input.value = input.value.replace(/[^a-zA-Z0-9]/g, '').slice(0, 7).toUpperCase();
    },
    name(input) {
      const particles = new Set(['da', 'de', 'do', 'das', 'dos', 'e']);
      input.value = input.value.replace(/\s+/g, ' ').toLocaleLowerCase('pt-BR').split(' ').map((part, index) => {
        if (!part || (index > 0 && particles.has(part))) return part;
        return part.charAt(0).toLocaleUpperCase('pt-BR') + part.slice(1);
      }).join(' ');
    },
    money(input) {
      const raw = input.value.replace(/[^\d,]/g, '');
      const parts = raw.split(',');
      input.value = parts.length > 1 ? parts[0] + ',' + parts[1].slice(0, 2) : parts[0];
    }
  };
  const rules = [
    { label: /nome completo/i, placeholder: 'Maria Silva', mask: 'name' },
    { name: /full_name|client_name|partner_name/, placeholder: 'Maria Silva', mask: 'name' },
    { label: /^nome$/i, action: /usuario/, placeholder: 'Maria Silva', mask: 'name' },
    { label: /cpf/i, placeholder: '000.000.000-00', mask: 'cpf' },
    { label: /cnpj/i, placeholder: '00.000.000/0001-00', mask: 'cnpj' },
    { label: /whatsapp|telefone|celular/i, placeholder: '(47) 9 0000 - 0000', mask: 'phone' },
    { label: /e-mail|email/i, placeholder: 'nome@email.com' },
    { label: /instagram/i, placeholder: '@usuario' },
    { label: /^cidade$/i, placeholder: 'Balneário Camboriú' },
    { label: /^estado$/i, placeholder: 'SC', mask: 'uf' },
    { label: /^cep$/i, placeholder: '88330-000', mask: 'cep' },
    { label: /placa/i, placeholder: 'ABC1D23', mask: 'plate' },
    { label: /^marca$/i, placeholder: 'Porsche' },
    { label: /^modelo$/i, placeholder: '911 Cabriolet' },
    { label: /^ano$/i, placeholder: '2024' },
    { label: /endere[cç]o/i, placeholder: 'Avenida Atlântica, 1000' },
    { label: /^site$|link interno|link da/i, placeholder: 'https://' },
    { label: /observa/i, placeholder: 'Anotação interna' },
    { label: /identificador externo/i, placeholder: 'Código da venda' },
    { label: /data comemorativa/i, placeholder: 'Dia das Crianças' },
    { label: /^t[ií]tulo$/i, placeholder: 'Título que aparece no aplicativo' },
    { label: /texto/i, placeholder: 'Texto curto publicado no aplicativo' },
    { label: /descri/i, placeholder: 'Descreva em uma frase' },
    { label: /^nome$/i, placeholder: 'Nome do cadastro' },
    { name: /amount$|base_price|^price\[/, placeholder: '0,00', mask: 'money' }
  ];
  document.querySelectorAll('form input, form textarea').forEach((input) => {
    if (['hidden', 'checkbox', 'radio', 'file', 'password', 'date', 'time', 'number'].includes(input.type)) return;
    const label = input.closest('.field')?.querySelector('label')?.textContent.trim() || '';
    const action = input.form?.getAttribute('action') || '';
    const rule = rules.find((item) => {
      if (item.name && !item.name.test(input.name || '')) return false;
      if (item.label && !item.label.test(label)) return false;
      if (item.action && !item.action.test(action)) return false;
      return Boolean(item.label || item.name);
    });
    if (!rule) return;
    if (!input.placeholder) input.placeholder = rule.placeholder;
    if (rule.mask) input.dataset.mask = input.dataset.mask || rule.mask;
  });
  document.querySelectorAll('[data-cpf]').forEach((input) => { input.dataset.mask = 'cpf'; });
  document.querySelectorAll('[data-phone]').forEach((input) => { input.dataset.mask = 'phone'; });
  document.querySelectorAll('[data-money]').forEach((input) => { input.dataset.mask = 'money'; });
  document.querySelectorAll('[data-person-name]').forEach((input) => { input.dataset.mask = 'name'; });
  const checkMask = (input) => {
    const kind = input.dataset.mask;
    const count = kind === 'uf' || kind === 'plate' ? input.value.length : input.value.replace(/\D/g, '').length;
    const minimum = { cpf: 11, cnpj: 14, phone: 10, cep: 8, uf: 2, plate: 7 }[kind];
    if (!minimum || input.value.trim() === '') {
      input.setCustomValidity('');
      return;
    }
    const ok = kind === 'phone' ? count === 10 || count === 11 : count >= minimum;
    input.setCustomValidity(ok ? '' : 'Complete no formato ' + (input.placeholder || 'indicado') + '.');
  };
  document.querySelectorAll('[data-mask]').forEach((input) => {
    const apply = () => {
      const mask = masks[input.dataset.mask];
      if (mask) mask(input);
    };
    input.addEventListener('input', () => {
      if (input.dataset.mask !== 'name') apply();
      input.setCustomValidity('');
    });
    input.addEventListener('blur', () => {
      apply();
      checkMask(input);
    });
    if (input.value && input.dataset.mask !== 'name') apply();
  });
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('click', (event) => {
      if (!event.target.closest('[type="submit"]')) return;
      form.querySelectorAll('[data-mask]').forEach(checkMask);
    });
  });
}

document.querySelectorAll('[data-done-close]').forEach((button) => {
  button.addEventListener('click', () => button.closest('[data-done]')?.remove());
});

bootPage();
bootMessage();

document.addEventListener('click', (event) => {
  const confirmButton = event.target.closest('[data-confirm]');
  if (confirmButton && !window.confirm(confirmButton.getAttribute('data-confirm'))) {
    event.preventDefault();
    return;
  }
  const link = event.target.closest('.nav a');
  if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  if (link.origin !== location.origin) return;
  event.preventDefault();
  visit(link.href);
});

let visitSeq = 0;
async function visit(url, push) {
  const seq = ++visitSeq;
  try {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
    if (seq !== visitSeq) return;
    const target = new URL(response.url, location.origin);
    if (!response.ok || target.pathname === '/entrar') {
      location.href = url;
      return;
    }
    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
    if (seq !== visitSeq) return;
    const next = doc.querySelector('main.content');
    const title = doc.querySelector('.page-head h1');
    const current = document.querySelector('main.content');
    const heading = document.querySelector('.page-head h1');
    if (!next || !title || !current || !heading) {
      location.href = url;
      return;
    }
    document.querySelector('.page-tools')?.replaceChildren();
    current.replaceWith(next);
    heading.className = title.className;
    heading.textContent = title.textContent;
    document.title = doc.title;
    const path = target.pathname;
    document.querySelectorAll('.nav a').forEach((item) => {
      const href = item.getAttribute('href') || '';
      item.classList.toggle('active', path === href || (href !== '/' && path.startsWith(href)));
    });
    if (push !== false) history.pushState(null, '', target.pathname + target.search);
    const frame = document.querySelector('.frame');
    if (frame) frame.scrollTop = 0;
    window.scrollTo(0, 0);
    bootPage();
  } catch (error) {
    location.href = url;
  }
}

window.addEventListener('popstate', () => { visit(location.href, false); });
