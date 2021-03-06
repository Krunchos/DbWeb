<?php

// Includes
include_once "utils/database.php";
include_once "models/Anlegg.php";
include_once "utils/cache.php";

// Klasse som representerer de ulike idrettene klubben driver med.
class Idrett {

  // Idrettsobjekter opprettes direkte fra $_POST ved registrering, eller fra
  // et assosiativt array som hentes fra eksisterende oppføringer i databasen.
  // Ulike objektvariabler benyttes basert på hvor dataene kommer fra.
  public function __construct($idrett = [], $fraDatabase = false) {
    $this->idrettskode = $fraDatabase ? $idrett["idrettskode"] : null;
    $this->navn        = $idrett["navn"];
  }


  // Metode for validering av felter.
  // Utføres kun når idrettsobjektet konstrueres fra $_POST ved registrering,
  // altså stoler vi på dataene dersom de kommer direkte fra databasen.
  private function valider() {
    $feil = [];

    // Idrettsnavn kan bestå av bokstaver, tall, bindestrek, apostrof, komma og punktum. Maks 100 tegn.
    if (!preg_match("/^[\wæøåÆØÅ '.,-]{1,100}$/i", $this->navn))
      $feil["navn"] = "Ugyldig idrettsnavn.";

    // Dersom noen av valideringene feilet, kast unntak og send med forklaringer.
    if (!empty($feil))
      throw new InvalidArgumentException(json_encode($feil));
  }


  // Metode for å lagre et idrettsobjekt til databasen.
  public function lagre() {

    // UPDATE-spørring dersom medlemsnummer finnes, INSERT-spørring ellers.
    try {
      $this->valider();

      if ($this->idrettskode)
        $this->oppdater();
      else
        $this->settInn();
    }

    // Feilkode 1062 - brudd på UNIQUE i databasen - idrettsnavnet eksisterer.
    // Kaster unntaket videre dersom det ikke er relatert til validering.
    catch (mysqli_sql_exception $e) {
      if ($e->getCode() == 1062)
        throw new InvalidArgumentException(json_encode(["navn" => "Idretten er allerede registrert"]));
      throw $e;
    }
  }


  // Metode for innsetting av nye idretter.
  private function settInn() {

    // SQL-spørring med parametre for bruk i prepared statement.
    $sql = "INSERT INTO idrett (navn) VALUES (?);";

    // Kobler til databasen og utfører spørringen.
    $con = new Database();
    $res = $con->spørring($sql, [$this->navn]);

    // Henter idrettskode fra nyinnsatt rad.
    $this->idrettskode = $res->insert_id;
  }


  // Metode for oppdatering av eksisterende idretter.
  private function oppdater() {

    // SQL-spørring med parametre for bruk i prepared statement.
    $sql = "
      UPDATE idrett
      SET navn = ?
      WHERE idrettskode = ?;
    ";

    // Verdier som skal settes inn.
    $verdier = [$this->navn, $this->idrettskode];

    // Kobler til databasen og utfører spørringen.
    $con = new Database();
    $con->spørring($sql, $verdier);
  }


  // Statisk metode for sletting av idretter fra databasen.
  public static function slett($idrettskode) {

    // Slett fra cache hvis objektet finnes.
    Cache::set("idrett", $idrettskode, null);

    // Kaller på en lagret prosedyre. Bruker prepared statement.
    $sql = "
      CALL slett_idrett(?);
    ";

    // Kobler til databasen og utfører spørringen.
    $con = new Database();
    $con->spørring($sql, [$idrettskode]);
  }


  // Metode for å slette en idrett fra databasen.
  public function slettDenne() {
    self::slett($this->idrettskode);
  }


  // Statisk metode for å finne en idrett gitt ved sin idrettskode.
  public static function finn($idrettskode) {

    // Returnerer idrett fra cache hvis det finnes der.
    if ($idrett = Cache::get("idrett", $idrettskode))
      return $idrett;

    // SQL-spørring med parametre for bruk i prepared statement.
    $sql = "
      SELECT idrettskode, navn
      FROM idrett
      WHERE idrettskode = ?;
    ";

    // Kobler til databasen og utfører spørringen.
    // Henter resultatet fra spørringen i et assosiativt array ($res).
    $con = new Database();
    $res = $con
      ->spørring($sql, [$idrettskode])
      ->get_result()
      ->fetch_assoc();

    // Oppretter nytt idrettsobjekt, lagrer i cache og returnerer.
    $idrett = $res ? new Idrett($res, true) : null;
    return Cache::set("idrett", $idrettskode, $idrett);
  }


  // Statisk metode for å liste opp alle idretter.
  public static function finnAlle() {

    // SQL-spørring for uthenting av alle idretter.
    $sql = "
      SELECT idrettskode, navn
      FROM idrett
      ORDER BY navn;
    ";

    // Kobler til databasen og utfører spørringen.
    // Henter resultatet fra spørringen i et assosiativt array ($res).
    $con = new Database();
    $res = $con
      ->spørring($sql)
      ->get_result()
      ->fetch_all(MYSQLI_ASSOC);

    // Returnerer et array av idrettsobjekter og lagrer i cache.
    return array_map(function($rad) {
      return Cache::set("idrett", $rad["idrettskode"], new Idrett($rad, true));
    }, $res);
  }

  public function getAnlegg() {
    return Anlegg::finnAlle(["idrettskode" => $this->idrettskode]);
  }

}

?>
