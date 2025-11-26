// public/modules/wpp_chat/assets/js/wpp_chat.js

function wppRefreshQr(instanceId, imgSelector, statusSelector, debugSelector) {
    debugSelector = debugSelector || '#debugArea';

    var $img    = $(imgSelector);
    var $status = $(statusSelector);
    var $debug  = $(debugSelector);

    if ($status.length) {
        $status.text('Atualizando...');
    }

    $.ajax({
        url: 'instancia-qr.php',
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: {
            id: instanceId,
            ajax: 1
        },
        success: function (res) {
            if ($debug.length) {
                $debug.text(JSON.stringify(res, null, 2));
                $debug.show();
            }

            if (res.error) {
                if ($status.length) {
                    $status.text('ERRO');
                }
                return;
            }

            var statusText = res.status || 'DESCONHECIDO';
            if ($status.length) {
                $status.text(statusText);
            }

            if (res.qr) {
                var src = res.qr;

                // Se vier só o base64 puro, adiciona prefixo
                if (src.indexOf('data:image') !== 0) {
                    src = 'data:image/png;base64,' + src;
                }

                if ($img.length) {
                    $img.attr('src', src);
                }
            } else {
                if ($img.length) {
                    $img.attr('src', '');
                }
            }
        },
        error: function (xhr, textStatus, errorThrown) {
            if ($status.length) {
                $status.text('ERRO AJAX');
            }
            if ($debug.length) {
                $debug.text(
                    'AJAX error: ' + textStatus + ' / ' + errorThrown +
                    '\n\n' + (xhr.responseText || '')
                );
                $debug.show();
            }
        }
    });
}



function wppSendMessageFromForm(formSelector, callback) {
    var form = document.querySelector(formSelector);
    if (!form) return false;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    var formData = new FormData(form);

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (callback) callback(xhr);
        }
    };
    xhr.send(formData);
    return false;
}

var WPPChat = (function(){
  function ajax(url, data, success, method) {
    method = method || 'POST';
    var xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    if (method === 'POST') {
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    }
    xhr.onreadystatechange = function(){
      if (xhr.readyState === 4) {
        try {
          var resp = JSON.parse(xhr.responseText || '{}');
          success && success(resp);
        } catch(e) {
          console.error(e);
        }
      }
    };
    var body = null;
    if (data && method === 'POST') {
      var params = [];
      for (var k in data) {
        if (!data.hasOwnProperty(k)) continue;
        params.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
      }
      body = params.join('&');
    }
    xhr.send(body);
  }

  function initCampanhaView() {
    var btnDisparar = document.getElementById('btnDispararCampanha');
    var btnTeste    = document.getElementById('btnTestarPrimeiro');
    var logEl       = document.getElementById('campanhaDisparoLog');

    function log(msg) {
      if (!logEl) return;
      logEl.innerHTML += '(' + new Date().toLocaleTimeString() + ') ' + msg + '<br>';
      logEl.scrollTop = logEl.scrollHeight;
    }

    function disparar(modo) {
      if (!btnDisparar) return;
      var campanhaId = btnDisparar.getAttribute('data-campanha-id');
      log('Iniciando disparo ('+modo+')...');
      ajax(
        baseUrl+'/public/modules/wpp_chat/pages/ajax/campanha-disparar.php',
        {campanha_id: campanhaId, modo: modo},
        function(resp){
          if (resp.ok) log('OK: ' + resp.msg);
          else log('ERRO: ' + resp.msg);
        }
      );
    }

    if (btnDisparar) {
      btnDisparar.addEventListener('click', function(){
        disparar('todos');
      });
    }
    if (btnTeste) {
      btnTeste.addEventListener('click', function(){
        disparar('teste');
      });
    }
  }

  // Script Builder
  function initScriptBuilder(xmlAtual) {
    var container = document.getElementById('scriptBuilderQuestions');
    var btnAdd    = document.getElementById('btnAddQuest');
    var xmlField  = document.getElementById('xml_definicao');

    function parseXmlToQuestions(xmlStr) {
      var qs = [];
      if (!xmlStr) return qs;
      var re = /<quest>([\s\S]*?)<\/quest>\s*<answer>([\s\S]*?)<\/answer>/g;
      var m;
      while ((m = re.exec(xmlStr)) !== null) {
        qs.push({
          quest: (m[1] || '').trim(),
          answer: (m[2] || '').trim()
        });
      }
      return qs;
    }

    function buildXmlFromQuestions() {
      var blocks = container.querySelectorAll('.wpp-script-block');
      var xml = '<script>\n';
      blocks.forEach(function(b){
        var q = b.querySelector('textarea[name="quest"]').value || '';
        var a = b.querySelector('textarea[name="answer"]').value || '';
        xml += '  <step>\n';
        xml += '    <quest>'+escapeHtml(q)+'</quest>\n';
        xml += '    <answer>'+escapeHtml(a)+'</answer>\n';
        xml += '  </step>\n';
      });
      xml += '</script>';
      xmlField.value = xml;
    }

    function escapeHtml(s) {
      return s.replace(/[&<>]/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;'}[c] || c;
      });
    }

    function addBlock(q, a) {
      var div = document.createElement('div');
      div.className = 'wpp-script-block border rounded p-2 mb-2';
      div.innerHTML =
        '<div class="mb-2">'+
          '<label class="form-label">Pergunta</label>'+
          '<textarea name="quest" class="form-control" rows="2"></textarea>'+
        '</div>'+
        '<div class="mb-2">'+
          '<label class="form-label">Resposta esperada / observações</label>'+
          '<textarea name="answer" class="form-control" rows="2"></textarea>'+
        '</div>'+
        '<div class="text-end">'+
          '<button type="button" class="btn btn-sm btn-outline-danger wpp-script-remove">Remover</button>'+
        '</div>';
      container.appendChild(div);

      var tq = div.querySelector('textarea[name="quest"]');
      var ta = div.querySelector('textarea[name="answer"]');
      if (q) tq.value = q;
      if (a) ta.value = a;

      div.addEventListener('input', buildXmlFromQuestions);
      div.querySelector('.wpp-script-remove').addEventListener('click', function(){
        div.parentNode.removeChild(div);
        buildXmlFromQuestions();
      });
    }

    if (btnAdd) {
      btnAdd.addEventListener('click', function(){
        addBlock('', '');
        buildXmlFromQuestions();
      });
    }

    // Carregar XML existente
    var qs = parseXmlToQuestions(xmlAtual || '');
    if (qs.length) {
      qs.forEach(function(q){ addBlock(q.quest, q.answer); });
    }

    // Evento geral do textarea XML => refazer blocos (opcional)
    if (xmlField) {
      xmlField.addEventListener('change', function(){
        container.innerHTML = '';
        var q2 = parseXmlToQuestions(xmlField.value);
        q2.forEach(function(q){ addBlock(q.quest, q.answer); });
      });
    }
  }

  // Chat frontend
  function initChatPage() {
    var thread = document.getElementById('wppChatThread');
    var form   = document.getElementById('wppChatSendForm');
    if (!thread || !form) return;

    var conversaId = thread.getAttribute('data-conversa-id');

    function renderMsgs(msgs) {
      thread.innerHTML = '';
      msgs.forEach(function(m){
        var div = document.createElement('div');
        div.className = 'wpp-chat-msg wpp-chat-msg-' + (m.direction === 'out' ? 'out' : 'in');
        var html = ''
          + '<div class="wpp-chat-msg-bubble">'
          + '  <div class="wpp-chat-msg-text">'+(m.conteudo || '').replace(/\n/g,'<br>')+'</div>'
          + '  <div class="wpp-chat-msg-meta"><small>'+m.data_msg+'</small></div>'
          + '</div>';
        div.innerHTML = html;
        thread.appendChild(div);
      });
      thread.scrollTop = thread.scrollHeight;
    }

    function poll() {
      var url = baseUrl+'/public/modules/wpp_chat/pages/ajax/conversas-poll.php?conversa_id='+encodeURIComponent(conversaId);
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.onreadystatechange = function(){
        if (xhr.readyState === 4) {
          try {
            var resp = JSON.parse(xhr.responseText || '{}');
            if (resp.ok) renderMsgs(resp.msgs || []);
          } catch(e) {}
          setTimeout(poll, 3000);
        }
      };
      xhr.send();
    }
    poll();

    form.addEventListener('submit', function(e){
      e.preventDefault();
      var texto = form.texto.value.trim();
      if (!texto) return;
      ajax(
        baseUrl+'/public/modules/wpp_chat/pages/ajax/conversas-send.php',
        {conversa_id: conversaId, texto: texto},
        function(resp){
          if (resp.ok) {
            form.texto.value = '';
            poll();
          } else {
            alert(resp.msg || 'Erro ao enviar mensagem.');
          }
        }
      );
    });
  }

  return {
    initCampanhaView: initCampanhaView,
    initScriptBuilder: initScriptBuilder,
    initChatPage: initChatPage
  };
})();
