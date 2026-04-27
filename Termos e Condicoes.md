Termos e Condições Gerais de Uso e Licenciamento
Data de disponibilização: 22/05/2024

Última atualização: 27/03/2026

Estes Termos e Condições de Uso (“Termos”) regulam a relação comercial e o licenciamento de
uso entre a empresa BIANCA SANT ANA PEREIRA & CIA LTDA, pessoa jurídica de direito
privado, inscrita no CNPJ sob o nº 35.617.749/0001-67, doravante denominada “Dominai Cloud” ou
“LICENCIANTE”, e a pessoa física ou jurídica que adquire a licença, doravante denominada
“CLIENTE” ou “LICENCIADO”.

O objeto deste instrumento é o regramento da utilização do software de gestão e automação de
atendimentos denominado “SISTEMA DOMINAI CLOUD”, fornecido na modalidade auto-hospedada (selfhosted).

AO CONTRATAR E UTILIZAR O SISTEMA DOMINAI CLOUD, O CLIENTE DECLARA TER LIDO, COMPREENDIDO E ACEITO INTEGRALMENTE ESTES TERMOS.

QUAIS AS INFORMAÇÕES QUE VOCÊ ENCONTRARÁ NESTES TERMOS
CONCEITOS IMPORTANTES NESTES TERMOS

NATUREZA E EFICÁCIA DOS TERMOS

REQUISITOS TÉCNICOS E CONDIÇÕES DE OPERAÇÃO

DELIMITAÇÃO DE RESPONSABILIDADES E RELAÇÃO COM TERCEIROS

OBJETO E LICENÇA DE USO

PLANOS, PAGAMENTO, RENOVAÇÃO E CANCELAMENTO

POLÍTICA DE SUPORTE TÉCNICO E SLA

OBRIGAÇÕES, RESPONSABILIDADES E LIMITAÇÕES

PROPRIEDADE INTELECTUAL

PRIVACIDADE E PROTEÇÃO DE DADOS (LGPD)

DISPOSIÇÕES GERAIS

FORO E LEGISLAÇÃO APLICÁVEL

1. CONCEITOS IMPORTANTES NESTES TERMOS
   Para facilitar a leitura e interpretação deste documento, adotamos as seguintes definições:

Cliente (ou Licenciado): Pessoa física ou jurídica que adquire a Licença de Uso do Software Dominai Cloud, responsável pelo pagamento, pela contratação da infraestrutura (VPS) e pela gestão dos Usuários e Clientes Finais (Tenants).

Sistema Dominai Cloud: Software desenvolvido e de propriedade exclusiva da Dominai Cloud, fornecido sob regime de licenciamento self-hosted. Trata-se de uma solução tecnológica para centralização e gestão de multicanais de atendimento e automação.

Licença de Uso Anual: Modalidade de contratação que concede ao Cliente o direito de uso do Sistema Dominai Cloud pelo período de 12 (doze) meses, instalada na infraestrutura providenciada pelo Cliente.

Modelo White-Label: Característica do Sistema Dominai Cloud que permite ao Cliente personalizar a identidade visual e revender o acesso ao software para terceiros sob sua própria marca.

Infraestrutura (VPS): Servidor Virtual Privado (Virtual Private Server) contratado e custeado diretamente pelo Cliente junto a terceiros, onde o Sistema Dominai Cloud será instalado.

Usuário: Pessoa autorizada pelo Cliente a acessar o painel do sistema (Super Admin, Admin, supervisores ou atendentes).

Tenant (Cliente Final): Conta ou instância criada pelo Cliente dentro do Sistema Dominai Cloud para revender serviços a terceiros.

2. NATUREZA E EFICÁCIA DOS TERMOS
   2.1. Ao adquirir a Licença de Uso, o Cliente concorda integralmente com estes Termos. A aceitação destas regras é condição indispensável para a liberação do acesso, instalação e utilização do Sistema Dominai Cloud.

2.2. A realização do pagamento ou o simples início da utilização do Sistema Dominai Cloud implica, para todos os fins de direito, na aceitação plena, inequívoca e irrevogável de todas as condições estabelecidas nestes Termos e suas futuras atualizações.

2.3. O Cliente reconhece que estes Termos possuem força de contrato vinculante, substituindo quaisquer acordos verbais ou trocas de mensagens anteriores.

2.4. Pela teoria da aparência, a Dominai Cloud considerará válida a contratação realizada mediante o fornecimento de dados cadastrais e pagamento, declarando o Cliente que a pessoa responsável pela compra possui plenos poderes para representá-lo.

2.5. A Dominai Cloud poderá alterar estes Termos a qualquer momento. O uso continuado do sistema após a publicação das alterações confirma a aceitação dos novos Termos.

3. REQUISITOS TÉCNICOS E CONDIÇÕES DE OPERAÇÃO
   Para garantir a performance, segurança e estabilidade do Sistema Dominai Cloud, o Cliente deve observar rigorosamente os seguintes requisitos técnicos.

3.1. Requisitos da Estação de Trabalho (Usuário):
Recomendamos que o sistema operacional e o navegador do Usuário estejam atualizados. As máquinas utilizadas para acessar o painel devem satisfazer, no mínimo:

Memória (RAM): 8GB ou mais.

Processador: Intel i5 ou superior (ou equivalente).

Conexão: Internet rápida e estável.

3.2. Requisitos do Servidor (Infraestrutura VPS):
A VPS onde o sistema será instalado deverá satisfazer os seguintes requisitos mínimos obrigatórios:

Memória (RAM): 16GB ou mais.

Processamento: 4 vCPUs ou mais.

Latência: 50ms ou menos (recomendado servidor no Brasil ou próximo).

Sistema Operacional: Ubuntu 20.04 ou 22.04 LTS (limpo, sem painel).

Armazenamento: SSD ou NVMe a partir de 200GB.

3.3. Documentação Técnica:
Os requisitos detalhados e atualizados constam no link oficial: https://dominai.cloud/requisitos-de-uso.

3.4. Versões Suportadas:
É crucial manter o sistema operacional e navegadores atualizados. As versões suportadas para conexão com a Plataforma WhatsApp são:

API WhatsApp Business Account v19.0 (ou superior).

WhatsApp Web v2.24xx.x (ou superior).

3.5. Veracidade das Informações:
O Usuário deve fornecer informações verdadeiras, exatas e atuais. A Dominai Cloud reserva o direito de recusar o cadastro ou cancelar contas cujos Usuários adotem condutas contrárias a estes Termos ou aos valores da empresa, sem necessidade de notificação prévia ou indenização.

3.6. Protocolos de Conexão (API Oficial vs. Não Oficial):

3.6.1. Padrão Recomendado: A Dominai Cloud foi desenvolvida para utilizar a API Oficial do WhatsApp (WABA) como padrão. A API Oficial garante maior estabilidade, segurança e suporte direto da Meta, sendo a única opção para a qual oferecemos garantia plena de funcionalidade.

3.6.2. APIs Não Oficiais (Riscos): Para maior flexibilidade, o sistema permite integração com APIs Não Oficiais (WWebJS, Baileys, Meow, Evolution, Uazapi, Z-API, etc). O uso dessas APIs é de total responsabilidade do Cliente, que deve estar ciente dos riscos, incluindo:

Desconexões frequentes;

Perda de mensagens enviadas ou recebidas;

Instabilidade no funcionamento após atualizações do app WhatsApp;

Possíveis bloqueios ou banimentos de contas pela Meta.

3.6.3. Isenção de Garantia: A Dominai Cloud não garante a estabilidade, o desempenho ou o suporte técnico para problemas decorrentes exclusivamente do uso de APIs Não Oficiais. Ao habilitar essa conexão, o Cliente assume integralmente os riscos e isenta a Dominai Cloud de responsabilidade por perdas ou danos.

4. DELIMITAÇÃO DE RESPONSABILIDADES E RELAÇÃO COM TERCEIROS
   4.1. Estes Termos disciplinam exclusivamente a relação comercial entre a Dominai Cloud e o Cliente (Licenciado).

4.2. Responsabilidade sobre Equipe: O Cliente é o único responsável pela seleção, contratação, remuneração e gestão de seus funcionários (Usuários/Atendentes) que utilizam a plataforma, isentando a Dominai Cloud de qualquer vínculo trabalhista ou solidariedade.

4.3. Responsabilidade sobre o Modelo White-Label (Tenants):
Considerando que o Cliente pode revender o uso do sistema (SaaS) sob marca própria:

4.3.1. A Dominai Cloud atua como fornecedora de tecnologia e não possui relação com os clientes finais (Tenants) do Cliente.

4.3.2. O Cliente é o único responsável pelo suporte, cobrança e cumprimento legal perante seus próprios clientes. Qualquer disputa judicial iniciada por um Tenant deverá ser assumida integralmente pelo Cliente.

5. OBJETO E LICENÇA DE USO
   5.1. Objeto: O contrato concede ao Cliente uma Licença de Uso de Software (Direito de Uso), não exclusiva, intransferível e temporária, do Sistema Dominai Cloud, para instalação em servidor (VPS) do Cliente.

5.2. Escopo: A licença permite instalar o software, configurar o White-Label, criar usuários ilimitados (conforme capacidade do servidor) e receber atualizações durante a vigência contratual.

5.3. Restrições:
Sob pena de cancelamento imediato e medidas judiciais, é vedado:

a) Copiar, vender ou distribuir o código-fonte ou o instalador do sistema.

b) Realizar engenharia reversa ou descompilação do código fonte.

c) Sublicenciamento e Distribuição: Vender, alugar, doar ou transferir os arquivos de instalação, o código-fonte ou a chave de licença para que terceiros realizem a instalação em infraestrutura própria (pirataria).

Única Modalidade Permitida (SaaS): A comercialização autorizada restringe-se exclusivamente à venda do acesso ao sistema (login/senha), com o software rodando obrigatoriamente em infraestrutura gerida pelo Cliente Licenciado.

5.4. Natureza Self-Hosted: O Cliente declara-se ciente de que a Dominai Cloud fornece o software, não a infraestrutura. A gestão do servidor (VPS) é de responsabilidade do Cliente.

5.5. Serviço de Setup de Instalação:
Além de disponibilizar o instalador automatizado e tutoriais na área de membros, caso o Cliente opte pela instalação agendada, a Dominai Cloud realizará, uma única vez, a instalação técnica remota do software no servidor indicado pelo Cliente. A execução deste serviço está condicionada, obrigatoriamente, à entrega do ambiente nas seguintes condições:

5.5.1. Estado do Servidor: A VPS deve ser entregue formatada (instalação limpa), contendo apenas o Sistema Operacional instalado, sem painéis de gestão (como cPanel, Plesk, CyberPanel) e sem outros serviços rodando (Apache, Nginx ou bancos de dados pré-instalados), para evitar conflitos de porta.

5.5.2. Especificações Obrigatórias:

Sistema Operacional: Ubuntu Server nas versões 20.04, 22.04 ou 24.04 LTS.

Hardware: Mínimo de 16GB de memória RAM.

5.5.3. Dados para Execução: O Cliente deverá fornecer previamente à equipe técnica:

I. IP da VPS e senha de acesso SSH (usuário ROOT).

II. Definição dos 02 (dois) subdomínios para a aplicação (Ex: app.dominio.com para frontend e api.dominio.com para backend), já com os apontamentos DNS (Tipo A) direcionados para o IP da VPS.

III. E-mail de cadastro na Dominai Cloud e número de WhatsApp para validação.

5.5.4. Método de Acesso: A instalação é realizada exclusivamente via protocolos de terminal (SSH). Não realizamos instalações via acesso remoto visual ou compartilhamento de tela (ex: AnyDesk, TeamViewer, Zoom), nem instalações em computadores locais (PC/Notebook).

5.5.5. Serviços Extra-Contratuais: Reinstalações, execução remota de atualizações, formatações, alterações de servidor ou migração de bancos de dados não estão cobertas pela licença. A Dominai Cloud não se obriga a realizar estes procedimentos, sendo a sua eventual execução mera liberalidade, sujeita à disponibilidade de agenda técnica e aprovação de orçamento extra.

5.5.6. Prazo de Execução: O prazo para realização da instalação pela equipe técnica é de até 03 (três) dias úteis. A contagem deste prazo inicia-se exclusivamente após o envio completo e a validação bem-sucedida de todas as credenciais e requisitos técnicos necessários. Caso os dados fornecidos estejam incorretos ou o servidor apresente falhas de conexão, a solicitação entrará em pendência e o prazo será reiniciado a partir da regularização pelo Cliente.

6. PLANOS, PAGAMENTO, RENOVAÇÃO E CANCELAMENTO
1. Natureza da Contratação e Licença Anual:
   A aquisição do Sistema Dominai Cloud é realizada através da compra de uma Licença de Uso Anual, disponibilizada em diferentes categorias de Planos, conforme a finalidade de uso do Cliente.

6.1.1. O valor da licença corresponde a um pagamento único referente ao ciclo de 12 (doze) meses.

6.1.2. Parcelamento ≠ Mensalidade: Caso o Cliente opte pelo pagamento parcelado via cartão de crédito (ex: em 12 vezes), ele declara estar ciente de que não se trata de uma assinatura mensal cancelável, mas sim de uma única compra parcelada, assumindo o compromisso de quitar a integralidade das parcelas perante a operadora do cartão, independentemente da utilização efetiva do sistema durante todo o período.

6.2. Escopo de Uso, Planos e Auditoria:
O limite de instâncias e os direitos comerciais de uso são estritamente determinados pela categoria do Plano adquirido pelo Cliente:

6.2.1. Modalidades de Licenciamento:

I. Plano "Uso Próprio" (Single-Tenant): Concede o direito de ativação de apenas 01 (uma) instância (Tenant) vinculada à chave de licença. Este plano é destinado exclusivamente à centralização e gestão do próprio negócio do Cliente, não havendo limites para a criação de usuários (atendentes) ou conexão de números telefônicos nesta mesma instância. É expressamente vedada a revenda, sublocação ou criação de contas para terceiros nesta modalidade.

II. Plano "Para Revenda" (Multi-Tenant): Concede o direito de criação e gestão de múltiplas instâncias (Tenants) de forma ilimitada. Este plano autoriza o Cliente a operar no modelo White-Label e revender acessos (SaaS) para múltiplos clientes finais. Todas as instâncias criadas devem estar vinculadas a subdomínios de um mesmo domínio raiz de titularidade do Cliente (ex: app1.cliente.com, app2.cliente.com), validado pela chave de licença no momento da ativação.

6.2.2. Intransferibilidade da Licença Matriz: A licença (chave de ativação) é pessoal, intransferível e vinculada ao CPF/CNPJ do Cliente adquirente. Mesmo no Plano "Para Revenda", o Cliente comercializa apenas o acesso (login) aos seus Tenants, sendo expressamente vedado o compartilhamento ou repasse da chave de licença para ativação em servidores ou domínios raízes de terceiros.

6.2.3. Auditoria e Penalidades: A Dominai Cloud reserva-se o direito de verificar o cumprimento destas cláusulas a qualquer momento (via telemetria ou auditoria de banco de dados). A constatação de uso indevido — a exemplo de um Cliente do "Plano Uso Próprio" comercializando acessos a terceiros, ou ativações em domínios não autorizados — poderá acarretar a suspensão imediata da licença e a cobrança proporcional à diferença de plano ou licenças extras utilizadas irregularmente.

6.2.4. Condições Especiais (Multi-domínio): A critério exclusivo da Dominai Cloud, poderão ser comercializadas licenças especiais ou oferecidos bônus promocionais que permitam a ativação em múltiplos domínios raízes dentro de uma mesma chave. Tais condições serão válidas apenas se expressamente descritas na oferta ou fatura correspondente.

6.3. Renovação da Licença:
A licença possui validade de 1 ano a contar da data da compra. Para garantir a continuidade do uso, o Cliente deverá renovar a licença ao final do ciclo.

6.3.1. Renovação Automática (Assinatura): O Cliente poderá optar, no momento da compra ou através do painel de controle, pela ativação da Renovação Automática. Neste modelo, a cobrança da nova anuidade será realizada automaticamente no método de pagamento cadastrado, garantindo a continuidade do serviço sem interrupções.

I. O Cliente pode desativar a renovação automática a qualquer momento antes da data de cobrança, sem penalidades, retornando ao modelo de renovação manual.

6.3.2. Antecipação de Renovação: O Cliente poderá, a seu critério, antecipar a renovação da licença antes do vencimento. Neste caso, o novo período de 12 meses será acrescido à data final da licença vigente (somando-se ao prazo restante), limitado a um acúmulo máximo de 24 (vinte e quatro) meses de validade total da chave de licença.

6.3.3. Bloqueio por Falta de Renovação: Caso não ocorra a renovação (manual ou automática) até a data de vencimento, a licença será desativada automaticamente no final do ciclo anual. O bloqueio da licença impede o acesso ao painel administrativo e interrompe imediatamente o funcionamento de todas as conexões, automações e APIs, até que a regularização financeira seja efetuada.

6.4. Política de Cancelamento e Reembolso (Venda Anual):

6.4.1. Prazo de Arrependimento (7 Dias): Em conformidade com o Art. 49 do Código de Defesa do Consumidor, o Cliente poderá solicitar o cancelamento e o reembolso integral do valor pago em até 07 (sete) dias corridos após a data da compra inicial.

6.4.2. Irrevogabilidade após 7 Dias: Decorrido o prazo de 7 dias, a licença é considerada plenamente entregue e consumada. Não haverá reembolso, estorno ou cancelamento de parcelas vincendas (pro rata) caso o Cliente decida deixar de usar o sistema antes do fim do período de 12 meses.

6.4.3. O pedido de cancelamento efetuado após o prazo legal de arrependimento terá efeito apenas de não renovação para o próximo ciclo, permanecendo o sistema disponível para uso até o término da vigência já contratada e paga.

6.5. Reajuste de Preços e Proteção de Valor:
A Dominai Cloud reserva-se o direito de reajustar o valor da licença para novos licenciamentos ou renovações, visando preservar o equilíbrio econômico do serviço frente a variações de mercado, inflação ou custos operacionais.

6.5.1. Aviso Prévio: Para Clientes com licença ativa, eventuais reajustes serão aplicados somente na data da próxima renovação. A Dominai Cloud compromete-se a comunicar qualquer alteração de preço com antecedência mínima via e-mail ou painel administrativo.

6.5.2. Travamento de Preço (Price Lock): O Cliente tem a opção de garantir o valor da tabela atual e proteger-se de futuros reajustes. Para isso, basta realizar a antecipação da renovação (conforme regras do item 6.3.2), o que permite "travar" o preço vigente pelo período contratado, até o limite máximo de 24 (vinte e quatro) meses acumulados.

6.6. Custos Variáveis de Terceiros:
Os valores cobrados pela Dominai Cloud referem-se exclusivamente à licença de software. Custos variáveis decorrentes de serviços de terceiros integrados à plataforma são de responsabilidade exclusiva do Cliente e podem sofrer alterações alheias à vontade da Dominai Cloud, tais como:

I. Tarifas da Meta/WhatsApp (janelas de conversação, WABA);

II. Consumo de APIs de Inteligência Artificial (OpenAI, Anthropic, Google, Grok, etc);

III. Serviços de SMS, Gateways de Pagamento e Provedores de VPS.

IV. Consumo de APIs externas gerenciadas por terceiros (Hub Notificame, Uazapi, Z-API, etc);

6.7. Inadimplência e Suspensão:
Na hipótese de falha no processamento de pagamentos parcelados ou estornos indevidos (Chargeback):

I. A Dominai Cloud suspenderá imediatamente o acesso à licença e ao suporte técnico.

II. A Dominai Cloud reserva-se o direito de recusar novas vendas a Clientes com histórico de inadimplência ou que tenham violado as regras de conduta e respeito com a equipe de suporte.

7. POLÍTICA DE SUPORTE TÉCNICO E SLA
   Este capítulo define o escopo, canais, horários e procedimentos para o acionamento do suporte técnico.

7.1. Canais e Horários:
O único canal oficial para solicitação de suporte técnico é através do nosso portal de tickets.

Horário de Atendimento: De Segunda a Sexta-feira, das 08h às 18h (Horário de Brasília), exceto feriados nacionais.

7.2. Tempo de Resposta (SLA):
Nosso prazo máximo para resposta a um novo chamado é de até 03 (três) dias úteis, contados a partir da abertura do ticket.

7.3. Escopo do Suporte (O que está incluso):
Nossa equipe está preparada e restrita para auxiliar com:

I. Esclarecimento de dúvidas sobre o funcionamento e os recursos nativos do sistema Dominai Cloud.

II. Investigação e correção de bugs ou falhas no código do sistema.

III. Auxílio em configurações relacionadas exclusivamente ao software Dominai Cloud.

7.4. Limitações (O que NÃO está incluso):
Para garantir a qualidade do atendimento, os seguintes itens estão fora do escopo do nosso suporte técnico padrão, sendo de responsabilidade do Cliente:

I. Configuração, gestão, manutenção ou segurança do servidor (VPS) do cliente.

II. Suporte para ferramentas de terceiros (Typebot, N8N, SIP, Webhooks externos, etc.).

III. Criação de fluxos de chatbot, lógicas de atendimento, implementação de automações ou consultoria de negócios/marketing.

IV. Acesso remoto à máquina do cliente (por questões de privacidade e segurança).

V. Customizações de frontend, alterações de código ou design (CSS/HTML) de forma personalizada para o cliente.

VI. Resolução de instabilidades ou falhas em APIs Não Oficiais. O funcionamento destas conexões depende de bibliotecas de terceiros e rotas não homologadas (simulação do WhatsApp Web), estando sujeitas a bloqueios e interrupções por parte da Meta que fogem ao controle técnico da Dominai Cloud.

VII. Suporte a ambientes locais e redes restritivas: Diagnóstico de problemas de conexão decorrentes de instalações em infraestrutura local (Localhost, servidores físicos on-premise, máquinas virtuais em PCs pessoais), bem como dificuldades relacionadas a redes sem IP público dedicado (CGNAT), túneis de conexão (Ngrok, Cloudflare Tunnel), proxies reversos customizados ou firewalls de borda corporativos que divirjam do ambiente padrão homologado (VPS Cloud com IP Público).

8. OBRIGAÇÕES, RESPONSABILIDADES E LIMITAÇÕES
   8.1. Obrigações da Dominai Cloud: Fornecer os arquivos de instalação, realizar o setup inicial, liberar atualizações (updates) de correção/segurança e prestar suporte técnico limitado ao funcionamento do software (bugs e dúvidas operacionais).

8.2. Obrigações do Cliente: Contratar e manter a VPS, realizar backups periódicos (a Dominai Cloud não tem acesso aos dados), gerenciar as integrações de terceiros e administrar os usuários.

8.3. Limitações sobre Integrações de Terceiros:
Conforme detalhado na Cláusula 3.6, a Dominai Cloud não possui controle sobre serviços externos (Meta/WhatsApp, OpenAI, Gateways).

8.3.1. A Dominai Cloud não será responsabilizada por indisponibilidade, mudanças de política, bloqueios ou banimentos de números aplicados por terceiros.

8.3.2. A indisponibilidade de serviços de terceiros não isenta o Cliente do pagamento da licença, nem gera direito a reembolso.

8.3.3. O Cliente declara ciência dos termos de uso do WhatsApp: https://www.whatsapp.com/legal/business-terms/

8.4. Riscos Inerentes e Dependência Tecnológica:

8.4.1. O Cliente reconhece que o sistema está sujeito a interferências, mau funcionamento ou atrasos inerentes ao uso da internet e infraestrutura de servidores.

8.4.2. O Cliente declara ter conhecimento desses riscos e concorda que falhas decorrentes de fatores externos não constituem defeito do software. Nestas hipóteses, não será devida pela Dominai Cloud qualquer indenização por prejuízos, incluindo, mas não se limitando a: danos materiais, lucros cessantes, perda de uma chance ou danos morais.

9. PROPRIEDADE INTELECTUAL
   9.1. Titularidade da Dominai Cloud:
   O Sistema Dominai Cloud, seu código-fonte, arquitetura de banco de dados, logotipos (da marca Dominai Cloud), documentações, manuais e APIs são de propriedade intelectual exclusiva da Dominai Cloud (Licenciante).

9.1.1. A contratação da licença não transfere, em hipótese alguma, a titularidade ou os direitos autorais do software para o Cliente. O Cliente recebe apenas uma autorização de uso (Licença), limitada, revogável e não exclusiva.

9.1.2. É vedado ao Cliente remover, ocultar ou alterar os avisos de copyright ou mecanismos de validação de licença embutidos no código-fonte, exceto nas áreas explicitamente designadas para personalização visual (White-Label).

9.2. Titularidade do Cliente (White-Label e Dados):
Pertencem exclusivamente ao Cliente:

I. A marca, logotipo e identidade visual aplicados sobre a plataforma no modelo White-Label.

II. A base de dados de contatos, históricos de conversas e informações de seus clientes finais (Tenants).

III. A estratégia de precificação e os contratos firmados com seus próprios clientes.

9.3. Auditoria:
A Dominai Cloud reserva-se o direito de monitorar remotamente, via telemetria do sistema, a validade da chave de licença, os subdomínios registrados e as estatísticas de logins realizados no sistema, respeitando a privacidade dos dados Cliente.

10. PRIVACIDADE E PROTEÇÃO DE DADOS (LGPD)
    10.1. Papéis das Partes (Definição de Controlador e Operador):
    Para fins da Lei Geral de Proteção de Dados (Lei nº 13.709/2018):

10.1.1. Dominai Cloud como Controladora: A Dominai Cloud atua como Controladora apenas dos dados cadastrais do Cliente Direto (nome, e-mail, CPF/CNPJ, telefone, domínio), utilizados para faturamento, validação da licença e suporte.

10.1.2. Cliente como Controlador: O Cliente é o único e exclusivo Controlador dos dados pessoais de terceiros (seus clientes finais/tenants, leads e contatos) que trafegam pelo Sistema Dominai Cloud instalado em seu servidor.

10.2. Ausência de Acesso aos Dados (Modelo Self-Hosted):
O Cliente reconhece que, devido à natureza auto-hospedada (self-hosted) do software:

10.2.1. O banco de dados fica hospedado na infraestrutura (VPS) contratada pelo Cliente, sob sua guarda e responsabilidade.

10.2.2. A Dominai Cloud não possui acesso ao conteúdo das mensagens, lista de contatos ou dados sensíveis processados pelo Cliente, exceto quando o Cliente fornece voluntariamente credenciais de acesso temporário para fins de suporte técnico.

10.3. Responsabilidade por Vazamentos:
Compete exclusivamente ao Cliente garantir a segurança de seu servidor (VPS), aplicando firewalls, atualizações de sistema operacional e boas práticas de segurança. A Dominai Cloud não se responsabiliza por vazamentos de dados decorrentes de invasões, falhas de segurança na VPS ou engenharia social sofrida pelo Cliente ou sua equipe.

11. DISPOSIÇÕES GERAIS
    A Dominai Cloud informa que poderá, a qualquer momento, unilateralmente, modificar/atualizar estes Termos e Condições de Uso. Caso isso aconteça, o Usuário com Plano de Assinatura vigente será notificado e a versão atualizada valerá a partir de sua publicação. A continuidade de acesso ou utilização do Dominai Cloud pelos Usuários, depois da divulgação, confirmará a aceitação e vigência dos novos Termos e Condições de Uso.

11.1. Comunicações:
Todas as comunicações, notificações e avisos serão considerados válidos quando enviados para os endereços de e-mail cadastrados pelo Cliente no momento da compra ou através de avisos no painel administrativo do sistema.

11.2. Tolerância:
A eventual tolerância da Dominai Cloud em exigir o cumprimento de qualquer cláusula deste contrato não configurará novação, renúncia ou perdão de direitos, podendo a cláusula ser exigida a qualquer tempo (não aplicação do supressio).

11.3. Conduta e Respeito:
A Dominai Cloud preza pelo respeito mútuo. Reserva-se o direito de suspender o suporte técnico ou, em casos extremos, rescindir a licença, caso o Cliente ou seus representantes tratem a equipe de atendimento com agressividade, ofensas, racismo, assédio ou qualquer comportamento inadequado.

11.4. Cessão:
O Cliente não poderá ceder ou transferir este contrato a terceiros sem a prévia autorização por escrito da Dominai Cloud. A Dominai Cloud poderá ceder este contrato em caso de fusão, aquisição ou venda de ativos da empresa.

11.5. Independência das Cláusulas:
Se qualquer disposição deste contrato for considerada inválida ou inexequível por um tribunal, as demais disposições permanecerão em pleno vigor e efeito.

12. FORO E LEGISLAÇÃO APLICÁVEL
    12.1. Este contrato é regido pelas leis da República Federativa do Brasil, em especial pelo Código Civil e pelas Leis de Propriedade Intelectual e Software.

12.2. Fica eleito o foro da Comarca de Alfenas - MG como o único competente para dirimir quaisquer dúvidas ou litígios oriundos deste contrato, com renúncia expressa a qualquer outro, por mais privilegiado que seja ou venha a ser.
