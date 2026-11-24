/*
 * Controle do Sintegra · avisos de documentos pendentes (Google Apps Script)
 *
 * Instale na conta que RECEBE os avisos (documentos.fiscais.pendentes). A cada 15 minutos o script
 * procura os e-mails "Documentos pendentes - EMPRESA - CNPJ ..." do EnviaArquivos e mostra no controle
 * que aquela empresa ficou com nota pendente no mês: quantidade, período e link para o e-mail.
 *
 * Como ligar (uma vez só), entrando no Google com a conta documentos.fiscais.pendentes:
 *   1. script.google.com > Novo projeto > cole este arquivo inteiro.
 *   2. Configurações do projeto (engrenagem) > Propriedades do script > adicionar
 *      CHAVE = a mesma chave de integração usada no script da conta envio.fiscal.
 *   3. Escolha a função "instalar" e clique em Executar. Autorize o acesso ao Gmail.
 *   4. (Opcional) Execute "importarHistorico" para trazer os avisos antigos.
 *
 * Os e-mails lidos recebem a etiqueta "Controle Sintegra" para não serem lidos de novo.
 */

var ENDERECO = 'https://goldtechsistemas.com/controle/?api=externo';
var BUSCA = 'subject:("Documentos pendentes" CNPJ)';
var ETIQUETA = 'Controle Sintegra';

// Liga a verificação automática a cada 15 minutos e já roda uma vez.
function instalar() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'verificar') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('verificar').timeBased().everyMinutes(15).create();
  verificar();
}

function verificar() { processar(BUSCA + ' newer_than:7d'); }

// Traz os avisos do último ano. Se parar no meio (limite de tempo do Google), rode de novo:
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
        var item = lerAviso(msg, conta);
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
  console.log('Avisos lidos: ' + total);
}

// Um e-mail "Documentos pendentes" vira um registro para o controle (ou null se não for um aviso).
function lerAviso(msg, conta) {
  var assunto = msg.getSubject();
  if (/^\s*(re|res|fw|fwd|enc)\s*:/i.test(assunto)) return null;
  var corpo = msg.getPlainBody() || '';
  var cnpj = (corpo.match(/CNPJ:\s*([\d.\/-]{14,18})/i) || assunto.match(/CNPJ:?\s*([\d.\/-]{14,18})/i) || [])[1];
  if (!cnpj) return null;
  var nome = ((corpo.match(/Empresa:\s*(.+)/i) || [])[1] || (assunto.match(/Documentos pendentes\s*-\s*(.+?)\s*-\s*CNPJ/i) || [])[1] || '').trim();
  var periodo = corpo.match(/Per[ií]odo:\s*(\d{2})\/(\d{2})\/(\d{4})\s*a\s*(\d{2})\/(\d{2})\/(\d{4})/i);
  var qtde = (corpo.match(/Qtde\.?\s*documentos pendentes:\s*(\d+)/i) || [])[1];
  var item = {
    cnpj: cnpj,
    nome_se_novo: nome,
    pendencia: {
      data: Utilities.formatDate(msg.getDate(), 'America/Sao_Paulo', 'yyyy-MM-dd'),
      inicio: periodo ? periodo[3] + '-' + periodo[2] + '-' + periodo[1] : '',
      fim: periodo ? periodo[6] + '-' + periodo[5] + '-' + periodo[4] : '',
      qtde: qtde ? Number(qtde) : 0,
      link: 'https://mail.google.com/mail/?authuser=' + encodeURIComponent(conta) + '#all/' + msg.getId(),
      assunto: assunto
    }
  };
  if (periodo) item.competencia = periodo[3] + '-' + periodo[2]; // período de outubro = entrega de novembro
  return item;
}
