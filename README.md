# swisspost
Schweizer Post API per PHP ansprechen

Weil ich noch nichts (Funktionierendes) zu dem Thema gefunden habe, hier meine Gedanken zur Schweizer Post API, um Versandetiketten zu erstellen.

Registrierung notwendig:
https://www.post.ch/de/geschaeftsloesungen/digital-commerce/digital-commerce-api/personalisierte-api-integration/digital-commerce-api-formular
 
## Verwendung

1. client_id, client_secret und Frankierlizenz in PHP-Skript eintragen, ggf. Standardabsender aktualisieren
2. swisspost_class.php nutzen. Aktuell zwei Methoden vorhanden: Adresse validieren und Etikett erstellen

```<?php
include 'swisspost_class.php';
$swp = new swisspost();

// Adresse validieren
$result = $swp->validateAddress(array("addressee" => array("title" => "", "firstName" => "", "lastName" => ""), "zip" => array("zip" => "4112"), "logisticLocation" => array("house" => array("street" => "Hauptstrasse", "houseNumber" => 18))));

// Etikett erstellen
$result = $swp->getLabel(array("firstName" => "Max", "name1" => "Mustermann", "street" => "Hauptstrasse 12", "zip" => "4112", "city" => "Flüh", "country" => "CH"));
?>
```

Infos über alle Adress-Attribute hier einsehbar: https://wedec.post.ch/doc/swagger/index.html?url=https://wedec.post.ch/doc/api/barcode/v1/swagger.yaml#/Barcode/generateAddressLabel
