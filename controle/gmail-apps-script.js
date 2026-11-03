/*
 * Controle do Sintegra · leitura do Gmail (Google Apps Script)
 *
 * Instale na conta que ENVIA os arquivos (envio.fiscal.goldtechsistemas). A cada 15 minutos
 * o script olha a aba Enviados dessa conta, procura os e-mails "Arquivos Empresa ... - CNPJ: ..."
 * e dá baixa no controle: data do envio, arquivos anexados e link para abrir o e-mail.
 *
 * Como ligar (uma vez só), entrando no Google com a conta envio.fiscal.goldtechsistemas:
 *   1. script.google.com > Novo projeto > cole este arquivo inteiro.
 *   2. Configurações do projeto (engrenagem) > Propriedades do script > adicionar
 *      CHAVE = a chave gerada no controle, botão "Integração".
 *   3. Escolha a função "instalar" e clique em Executar. Autorize o acesso ao Gmail.
 *   4. (Opcional) Execute "importarHistorico" para trazer os envios antigos.
 *
 * Os e-mails lidos recebem a etiqueta "Controle Sintegra" para não serem lidos de novo.
 */

var ENDERECO = 'https://goldtechsistemas.com/controle/?api=externo';
var BUSCA = 'in:sent subject:("Arquivos Empresa" CNPJ)';
var ETIQUETA = 'Controle Sintegra';
var MESES = ['janeiro', 'fevereiro', 'marco', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

// Liga a verificação automática a cada 15 minutos e já roda uma vez.
function instalar() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'verificar') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('verificar').timeBased().everyMinutes(15).create();
  verificar();
}

function verificar() { processar(BUSCA + ' newer_than:7d'); }

// Traz os envios do último ano. Se parar no meio (limite de tempo do Google), rode de novo:
// o que já foi lido é pulado.
function importarHistorico() { processar(BUSCA + ' newer_than:400d'); }

function processar(busca) {
  var chave = PropertiesService.getScriptProperties().getProperty('CHAVE');
  if (!chave) throw new Error('Falta a CHAVE: Configurações do projeto > Propriedades do script.');
  var etiqueta = GmailApp.getUserLabelByName(ETIQUETA) || GmailApp.createLabel(ETIQUETA);
  var conta = Session.getActiveUser().getEmail();
  var inicio = Date.now(), total = 0;

  // De 50 em 50 conversas, um envio só para o controle; para antes do limite de 6 minutos do Google.
  while (Date.now() - inicio < 4.5 * 60 * 1000) {
    var conversas = GmailApp.search(busca + ' -label:"' + ETIQUETA + '"', 0, 50);
    if (!conversas.length) break;
    var itens = [];
    conversas.forEach(function (conversa) {
      conversa.getMessages().forEach(function (msg) {
        var item = lerEnvio(msg, conta);
        if (item) itens.push(item);
      });
    });
    if (itens.length) {
      var resp = UrlFetchApp.fetch(ENDERECO, {
        method: 'post', contentType: 'application/json; charset=utf-8',
        headers: { 'X-Chave': chave }, payload: JSON.stringify(itens), muteHttpExceptions: true
      });
      // Sem resposta boa do servidor, não marca nada: tenta de novo na próxima rodada.
      if (resp.getResponseCode() !== 200) throw new Error('Controle respondeu ' + resp.getResponseCode() + ': ' + resp.getContentText());
      JSON.parse(resp.getContentText()).resultados.forEach(function (r) {
        if (!r.ok) console.warn('Não gravado (' + r.cnpj + '): ' + r.erro);
      });
      total += itens.length;
    }
    etiqueta.addToThreads(conversas);
  }
  console.log('Envios lidos: ' + total);
}

// Um e-mail de envio vira um registro para o controle (ou null se não for um envio).
function lerEnvio(msg, conta) {
  if (msg.getFrom().toLowerCase().indexOf(conta.toLowerCase()) < 0) return null; // respostas de outras pessoas
  var assunto = msg.getSubject();
  if (/^\s*(re|res|fw|fwd|enc)\s*:/i.test(assunto)) return null;
  var cnpj = (assunto.match(/CNPJ:\s*([\d.\/-]{14,18})/i) || [])[1];
  if (!cnpj) return null;
  var nome = ((assunto.match(/Arquivos Empresa\s+(.+?)\s+-\s+CNPJ/i) || [])[1] || '').trim();
  var arquivos = msg.getAttachments({ includeInlineImages: false }).map(function (a) { return a.getName(); });
  var item = {
    cnpj: cnpj,
    nome_se_novo: nome,
    gerado_em: Utilities.formatDate(msg.getDate(), 'America/Sao_Paulo', 'yyyy-MM-dd'),
    envio: {
      arquivos: arquivos,
      link: 'https://mail.google.com/mail/?authuser=' + encodeURIComponent(conta) + '#all/' + msg.getId(),
      assunto: assunto
    }
  };
  var comp = competencia(arquivos);
  if (comp) item.competencia = comp; // sem ela, vale o mês do e-mail
  return item;
}

// "Outubro2026_EMPRESA.zip" ou "SPED FISCAL OUTUBRO 2026.txt" -> "2026-10"
function competencia(arquivos) {
  for (var i = 0; i < arquivos.length; i++) {
    var nome = arquivos[i].normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    var m = nome.match(new RegExp('(' + MESES.join('|') + ')\\s*[-_ /]?\\s*(\\d{4})'));
    if (m) return m[2] + '-' + ('0' + (MESES.indexOf(m[1]) + 1)).slice(-2);
  }
  return null;
}
