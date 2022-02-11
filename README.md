# <img src="docs/Logo.png" width=50> PagoPA Gateway 
**PagoPa Gateway** è un   plugin per **WooCommerce** che permette di integrare in un e-commerce un metodo di pagamento basato sul portale dei pagamenti di *Cineca* chiamato **PagoAtenei**.

Il plugin può essere usato su siti realizzati con *WordPress* e *WooCommerce*.
Il plugin richiede anche che l'Ente attivi con *Cineca* il servizio **PagoAtenei.

**PagoPa Gateway** permette ai clienti di un e-commerce di pagare con **PagoPA** usando una **credit card** oppure stampando l'avviso di pagamento e pagandolo  **offline** (per esempio in una ricevitoria).

Il progetto è nato dalla necessità di permettere ai clienti del sito "**Edizioni**" ([edizioni.sns.it](https://edizioni.sns.it)) di pagare gli ordini con **PagoPA**.


## Stato del progetto
Il plugin è in fase di test.

## Funzionalità
- Pagamento degli ordini con **PagoPA** (online e offline).
- Form per configurare la connessione al gateway **Cineca** e altri parametri di funzionamento del plugin.
- configurazioni distinte per l'ambiente di test e l'ambiente di produzione.
- Gestione del workflow di pagamento.
- Procedura schedulabile per gestire e aggiornare gli ordini pagati offline.
- Gestione della notifica asincrona dei pagamenti da parte di PagoAtenei.
- Gestione della conferma sincrona dei pagamenti.
- Internazionalizzazione di etichetti e messaggi (italiano e inglese).
- Maschera per il controllo delle transazioni nel backoffice di Wordpress.
- Docker per testare il plugin velocemente.

## Prima attivazione del sistema
1. Concordare con *Cineca* l'attivazione del servizio PagoAtenei e degli ambienti di test e di produzione. I dati forniti da *Cineca* sono:
   - Una username per utilizzare l'Api Soap.
   - Una password per utilizzare l'api Soap.
   - Il WSDL del servizio.
   - Un certificato SSL con la relativa password.
2. Attivare sul portale dei pagamenti (sia in ambiente di test che di produzione) un Motivo e un Modello da associare ai pagamenti dell'e-commerce. Il codice del Modello è uno dei parametri di confgurazione del plugin.
3. Assicurarsi che i requisiti software siano soddisfatti dal proprio sistema (vedere il paragrafo "***Requisiti software***" di questo documento).
4. Scaricare, installare e configurare il plugin come descritto nel paragrafo "***Installazione e configurazione***" di questo documento.

## Requisiti software
1. Il CMS Wordpress (versione >= 5.6.6).
2. Il plugin Woocommerce (versione >= 5.0.0) per Wordpress .
3. Un web server Apache (o equivalente) con le estensioni *mod_ssl* e *soap* installate e abilitate.
4. Leggere la sezione "***Campi personalizzati***".

## Campi personalizzati
Il plugin usa i seguenti campi per compilare la richiesta inviata a PagoAtenei per la creazione di un pagamento:
 - **_billing_ita_cf**: Il Codice Fiscale per le persone fisiche.
 - **_billing_vat** : La Partita Iva per le aziende.
  
Edizioni usa un altro plugin personalizzato che aggiunge questi meta tag all'ordine, ma purtroppo quel plugin non può essere pubblicato in riuso.
Se questi campi non sono specificati, il plugin funziona lo stesso, ma di default un cliente verrà considerato come una persona con **Codice Fiscale = Nome + Cognome**.

## Intallazione e configurazione
1. [Scaricare](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/archive/refs/heads/main.zip) l'ultima versione stabile del plugin.
2. Scompattare il contenuto dell'archivio nella cartella **wp-content/plugins**.
3. Attivare il plugin dall'interfaccia di amministrazione di Wordpress.
4. Configurare il plugin dall'interfaccia di amministrazione di Wordpress (*W->WooCommerce->Impostazioni->Pagamenti->PagoPA Gateway->Gestisci*):
   - **Abilita/Disabilita**: Flag per abilitare o disabilitare il plugin.
   - **Titolo**: Il nome del metodo di pagamento è mostrato nella pagina di checkout d un ordine.
   - **Descrizione in inglese**: Una descrizione in inglese del metodo di pagamento, è mostrata nella versione inglese della pagina di checkout di un ordine.
   - **Descrizione**: Una descrizione in italiano del metodo di pagamento, è mostrata nella versione italiana della pagina di checkout di un ordine.
   - **Abilita la modalità di test**: Flag per abilitare o disabilitare la modalità di test e la connessione all'ambiente di test di *Cineca*.
   - **Metodi di conferma del pagamento**: 
   - - **Polling su PagoAtenei**: L'ordine è considerato valido se la callback del plugin è invocata da PagoAtenei con un token valido e se esiste un ordine in attesa di pagamento con quel numero d'ordine e quello Iuv. Può essere attivato un controllo ulteriore sulo stato del pagamento abilitando il flag "***Conferma di pagamento***".
   - - **Notifica asincrona di PagoAtenei**: L'ordine è considerato pagato solo se PagaoAtenei incia un messaggio **paNotificaTransazione** con **esito=PAGAMENTO_ESEGUITO**.
   - **Conferma di pagamento**: Se impostato, la callback invocata da *PagoAtenei* dopo un pagamento resta in attesa che il pagamento sia propagata dal PSP a *PagoAtenei*. Questa verifica è effettuata con un polling su *PagoAtenei* (gpChiedistatoVersamento), se non impostato, il plugin considera l'ordine pagato senza ulteriori controlli.
   - **Codice applicazione**: Il codice applicazione fornito da *Cineca*.
   - **Codice di dominio**: La Partita Iva dell'Ente.
   - **Iban**: L'Iban dell'Ente.
   - **Tipo contabilità**: Tipo di contabilità come definita dalla tassonomia di PagoPA consultabile [qui](https://github.com/pagopa/pagopa-api/blob/develop/taxonomy/tassonomia.json).
   - **Nome del file del certificato**: Il nome del certificato ***pem*** fornito da Cineca. Se il certificato fornito fosse nel formato *pk12* allora dovrebbe essere convertito nel formato *pem*.
   - **Password del certificato**: La password del certificato fornita da *Cineca*.
   - **Prefisso dell'ordine**: Un prefisso aggiunto al numero d'ordine di WooCommerce rpima che sia inviato al gateway di pagamento. E' utile per distinguere gli ordini di più istanze dello stesso sito, specialmente in fase di test. Può essere vuoto.
   - **Chiave di crittografia**: La chiave usata per criptare il token inviato al gateway.
   - **Token dell'API del plugin**: Il token usato per autenticare l'invocazione delle azioni schedulate e la REST API del plugin. Se vuoto la funzionalità è disabilitata. 
  
  **Credenziali di produzione**
   - **Indirizzo base del front end Cineca**: L'url del front-end di *PagoAtenei*. E' fornito da *Cineca*.
   - **Url di PagoAtenei**: L'indirizzo base dei web services Soap di *PagoAtenei*. E' fornito da *Cineca*.
   - **PagoAtenei API username**: Lo username da usare per invocare i web services di *PagoAtenei*. E' fornito da *Cineca*.
   - **Password di Pagoatenei**: La password da usare per invocare i web services di *PagoAtenei*. E' fornita da *Cineca*.
   - **Username dell'API del plugin**: Lo username dell'account che PagoAtenei deve utilizzare per invocare l'entry-point paNotificaTransazione. Deve essere comunicato a *Cineca*.
   - **Password dell'API del plugin**: La password dell'account che PagoAtenei deve utilizzare per invocare l'entry-point paNotificaTransazione. Deve essere comunicata a *Cineca*.
    - **ID del modello di pagamento**: L'ID del Modello di pagamento denito nel backoffice di *PagoAtenei* relativo agli ordini dell'e-commerce.
  
  **Credenziali di test**
   - **Indirizzo base del front end Cineca**: L'url del front-end di *PagoAtenei*. E' fornito da *Cineca*.
   - **Url di PagoAtenei**: L'indirizzo base dei web services Soap di *PagoAtenei*. E' fornito da *Cineca*.
   - **PagoAtenei API username**: Lo username da usare per invocare i web services di *PagoAtenei*. E' fornito da *Cineca*.
   - **Password di Pagoatenei**: La password da usare per invocare i web services di *PagoAtenei*. E' fornita da *Cineca*.
   - **Username dell'API del plugin**: Lo username dell'account che PagoAtenei deve utilizzare per invocare l'entry-point paNotificaTransazione. Deve essere comunicato a *Cineca*.
   - **Password dell'API del plugin**: La password dell'account che PagoAtenei deve utilizzare per invocare l'entry-point paNotificaTransazione. Deve essere comunicata a *Cineca*.
    - **ID del modello di pagamento**: L'ID del Modello di pagamento denito nel backoffice di *PagoAtenei* relativo agli ordini dell'e-commerce.
## conferma del pagamento: configurazioni possibili
- **Metodo di conferma del pagamento** = ***Notifica asincrona di PagoAtenei*** and **Payment confirmation** = ***falso***: L'ordine è considerato pagato solo quando PagoAtenei invoca l'entry-point ***paNotificaTransazione***. Il sito deve essere ospitato sun un server pubblico e bisogna chiedere a *Cineca* di attivare e configurare il messaggio ***paNotificaTransazione***. Non è possibile provare questa configurazione in un ambiente di sviluppo locale.
- **Metodo di conferma del pagamento** = ***Polling su PagoAtenei*** and **Conferma del pagamento** = ***falso***: L'ordine è considerato pagato solo se la callback è invocata correttamente da *PagoAtenei* e l'ordine è nello stato corretto. Non sono effettuati controlli aggiuntivi.
- **Metodo di conferma del pagamento** = ***Polling su PagoAtenei*** and **Conferma del pagamento** = ***vero***: La callback, dopo aver controllato il token e lo stato dell'ordine, interroga in polling *PagoAtenei* fino a quando *PagoAtenei* non riceve la notifica del pagamento dal **PSP**.

La prima di quelle elencate è la configurazione consigliata e quella più sicura.
## Schemi del flusso
Le immagini seguenti spiegano graficamente il flusso e il funzionamento del sistema:
- [Schema degli stati](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/docs/schema/SchemaDegliStati.png)
- [Schema dei pagamenti](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/docs/schema/SchemaDeiPagamenti.png)

## Come provare l'API Soap di PagoAtenei
Dopo aver richiesto ed ottenuti i parametri di connessione da *Cineca*, è possibile usare il programma SoapUI per provare i web services. Nella cartella *setup\TestSoap* si trova un progetto che può essere importato e usato in SoapUI. In alternativa è possibile creare un nuovo progetto utlizzando il seguente file [WSDL](https://gateway.pp.pagoatenei.cineca.it/portalepagamenti.server.gateway/api/private/soap/GPAppPort?wsdl).


## Entry pointe e callback
Il plugin espone i seguenti entry-point:

1. HOOK_PAYMENT_COMPLETE --> pagopa_payment_complete: è la callback invocata da PagoAtenei quando un oordine è pagato o cancellato.

2. HOOK_SCHEDULED_ACTIONS --> pagopa_execute_actions: è l'entry-point che può essere invocato da un cronjob per gestire gli ordini pagati offline.

3. HOOK_TRANSACTION_NOTIFICATION --> pagopa_notifica_transazione: è l'entry-point invocato da *PagoAtenei* per notificare il pagamento di un ordine.



## Galleria
![Enable](docs/screenshots/EnablePlugin_1.png)

**Image 1:** Backoffice: abilitazione del plugin.


![configure](docs/screenshots/ConfigurePlugin_1.png) 

**Image 2:** Backoffice: configurazione del plugin.

![transactions](docs/screenshots/Transactions.png)

**Image 3:** Backoffice: controllo delle transazioni.


## Documentazione
- Controllare la cartella ***doc*** di questo plugin.
- controllare la cartella ***setup/TestSoap** per il progetto SoapUI per provare l'API Soap.
- visitare il sito di *Cineca* per la documentazione e le specifiche sull'API Soap:
	- [Modalità di Integrazione](https://wiki.u-gov.it/confluence/pages/releaseview.action?pageId=329846832)
	- [WS pago-ATENEI Applicazioni](https://wiki.u-gov.it/confluence/display/public/UGOVINT/WS+pago-ATENEI+Applicazioni)

## Demo
### Docker
E' possibile provare il plugin usando un container *Docker* che contiene tutte le componenti software richieste (Wordpress + WooCommerce + wp-pagopa-gateway-cineca). 
Il Dockerfile da usare è: [Dockerfile](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/blob/main/setup/Docker/Dockerfile).

I comandi da eseguire per creare ed eseguire il container sono:
- docker build -t myshop-img -f Dockerfile .
- docker run -p 80:80 -p 3306:3306 --name=myshop -d myshop-img
 
Per collegarsi alla shell del container, eseguire il comando:
- docker exec -it myshop /bin/bash
  
L'url dell'e-commerce appena creato è: http://localhost/myshop/ .

Per provare il plugin è necessario abilitarlo e configurarlo con i dati fornitin da *Cineca*.

Per autenticarsi come amministratore del sito l'url è http://localhost/wp-admin/ e l'account is: manager / password

Sul container è installato il tool Adminer per gestire le tabelle del databse.
L'url di Adminer è: http://localhost/adminer.php
Per configurarlo i parametri sono:
- System: Mysql
- Server: 127.0.0.1
- Utente: admin
- Password: admin
- Database: myshop


## Catalogo del riuso
Il progetto è pubblicato sul catalogo del riuso di Developers Italia. La home page del progetto è [questa](https://developers.italia.it/it/software/sns_pi-scuolanormalesuperiore-wp-pagopa-gateway-cineca).
## Repository
[Questo](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca) è il repository che contiene il codice sorgente del progetto.
## Licenza
Il progetto è pubblicato sotto la licenza GPL-3.0-only come descritto nel file [LICENSE](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/blob/main/LICENSE).
## Lingue supportate
Il plugin è disponibile in italiano e in inglese.
[Questa](https://github.com/ScuolaNormaleSuperiore/wp-pagopa-gateway-cineca/blob/main/README_en.md) è la guida in inglese.

## Come contribuire
Lo scopo principale di questo repository è quello di far evolvere il plugin. Noi vogliamo rendere il processo per contribuire al progetto il più semplice e trasparente possibile e saremo grati alla comunità di coloro che vorranno contribuire alla soluzione di bug, al miglioramento del codice e all'aggiunta di nuove funzionalità.
## Copyright
1. Detentore copyright: Scuola Normale Superiore
2. Responsabili del progetto: Michele Fiaschi, Claudio Battaglino, Alida Isolani, Marcella Monreale
