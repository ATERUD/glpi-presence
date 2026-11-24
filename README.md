# Plugin GLPI "Presenza in sede"

Mostra un badge **In sede / Non in sede** nella scheda utente e tra i richiedenti dei ticket,
interrogando un endpoint HTTP esistente. Compatibile con GLPI 11.0.x (PHP 8.2+) e GLPI 12.0.x (PHP 8.3+, requisito di GLPI 12).

## Installazione

1. Copia la cartella `presence` in `glpi/plugins/` (il nome della cartella deve restare `presence`).
2. In GLPI: *Configurazione > Plugin* > Installa e Attiva "Presenza in sede".
3. Apri la configurazione del plugin (ingranaggio) e imposta l'endpoint.

## Configurazione

| Campo | Descrizione |
|---|---|
| URL endpoint | Es. `https://server/api/presence?user={user}`. `{user}` viene sostituito; se manca si aggiunge `?user=` |
| Identificativo | Cosa inviare: login, email predefinita, matricola o ID GLPI |
| Header di autenticazione | Opzionale. Es. nome `Authorization`, valore `Bearer xxxx` (salvato cifrato con la chiave GLPI) |
| Percorso JSON | Dove leggere il valore, in notazione puntata (es. `data.present`). Vuoto = tutta la risposta |
| Cache | Secondi di cache per utente, per non chiamare l'endpoint a ogni apertura di pagina |
| Timeout / SSL | Timeout della chiamata e verifica del certificato |

Valori interpretati come "presente": `true, 1, yes, si, presente, present, in, on, online`.
Come "assente": `false, 0, no, assente, absent, out, off, offline`. Qualsiasi altro valore
mostra un badge grigio "non disponibile".

Esempio di risposta accettata:

```json
{"present": true}
```

Test rapido dell'endpoint:

```bash
curl -H "Authorization: Bearer xxxx" "https://server/api/presence?user=mario.rossi"
```

## Permessi

La presenza è visibile solo ai **profili con il diritto "Presenza in sede"**:
*Amministrazione > Profili >* (profilo, es. Supervisor) *> scheda "Presenza in sede" >* "Può vedere la presenza" = Sì.

Chi non è autorizzato non vede il badge e l'endpoint non viene interrogato
(il controllo è anche lato server in `ajax/status.php`). Le modifiche ai profili si applicano al successivo login.
Alla prima installazione il diritto viene concesso al profilo di chi installa il plugin.

## Note

- Il badge viene caricato in AJAX (`ajax/status.php`): un endpoint lento non rallenta l'apertura della pagina.
- Gli errori di chiamata sono scritti in `files/_log/presence.log`.
- Con GLPI 12 le chiamate dal server verso reti interne possono essere bloccate dalle protezioni di GLPI.
  Se l'endpoint è su un indirizzo interno e il badge resta grigio, controlla `files/_log/presence.log`
  e la configurazione di GLPI per gli indirizzi consentiti.
- I testi sono in italiano come stringhe sorgente; per altre lingue si possono aggiungere i file in `locales/`.
