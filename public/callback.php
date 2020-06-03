<?php
include_once(__DIR__ . '/../src/load.php');


//cutly
$cutly = new cutURL();

//fichier PDF
$temp_pdf = sys_get_temp_dir() . '/' . uniqid('sell') . '.pdf';
//expression reguliere pour devis
$exp_devis = "#Devis n° (D[A-Z][0-9]{5}[a-z]?) du [0-9]{2}/[0-9]{2}/[0-9]{4}#";
//expression reguliere pour accompte
$exp_accompte = "#Acompte demandé : ([0-9]{1,5},[0-9]{2}) €#";



//test si token passer en param
if ($_GET['token'] === $_SERVER['APP_TOKEN']) {


    if (!isset($_FILES['pdf'])) {
        $log->error('Pas de fichier PDF');
        mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Erreur fichier', 'Pas de fichier Pdf');
    } elseif ($_FILES['pdf']['type'] != 'application/pdf') {
        $log->error('Pas de fichier PDF');
        mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Erreur fichier', 'le fichier est pas un PDF : ' . print_r($_FILES['pdf'], true));
    } else {

        //on bouge le fichier en temp
        move_uploaded_file($_FILES['pdf']['tmp_name'], $temp_pdf);

        //test si on a bien les valeur pour envoie du mail
        if ((isset($_POST['firstname']) && $_POST['firstname'])
            && (isset($_POST['lastname']) && $_POST['lastname'])
            && (isset($_POST['email']) && $_POST['email'])
            && (isset($_POST['cell_phone']) && $_POST['cell_phone'])
        ) {


            //Lecture du pdf
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($temp_pdf);

            //on obtiens les pages
            $pages  = $pdf->getPages();
            $p1 = $pages[0];

            //test si code devis trouver
            if (preg_match($exp_devis, $p1->getText(), $tab_devis)) {
                $num_devis = $tab_devis[1];


                //recherche de accompte
                if (preg_match($exp_accompte, $p1->getText(), $tab_accompte)) {
                    $mt_accompte = explode(',', $tab_accompte[1]);
                    //url de generation du paiuement
                    $url_paiement = $_SERVER['CB_URL'] . '?total=' . $mt_accompte[0] . $mt_accompte[1] . '&email=' . urlencode($_POST['email']) . '&ref_paiment=' . urlencode($num_devis);
                    $tinyurl = $cutly->cut($url_paiement);

                    $url_mail = $tinyurl['status'] == 7 && $tinyurl['shortLink'] ? $tinyurl['shortLink'] : $url_paiement;

                    $email = new Swift_Message('Diruy : Payer Votre accompte');
                    $email->setFrom($_SERVER['MAILER_EMAIL_DEVIS_EXP'])
                        ->setTo($_POST['email'])
                        ->setBody('Madame, Monsieur,
                        Merci de votre confiance.
                        Votre commande sera définitivement enregistrée après règlement de l’acompte dû soit ' . $mt_accompte[0] . ',' . $mt_accompte[1] . '&euro;.<br>
                        Afin de procéder au paiement en ligne, merci de vous rendre sur : ' . $url_mail)
                        ->addPart('Madame, Monsieur,<br>
                    Merci de votre confiance.<br>
                    Votre commande sera définitivement enregistrée après règlement de l’acompte dû soit ' . $mt_accompte[0] . ',' . $mt_accompte[1] . '&euro;.<br>
                    <a href="' . $url_mail . '">Afin de procéder au paiement en ligne, merci de cliquer ici.</a>
                    ','text/html');
                    $mailer->send($email);
                } else {
                    mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Erreur fichier', 'Pas de numero montant accompte .....');
                    $log->error('Pas de numero montant accompte .....');
                }
            } else {
                mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Erreur fichier', 'Pas de numero de devis pour la requete .....');
                $log->error('Pas de numero de devis pour la requete .....');
            }
        } else {
            $log->error('Il manque des valeur dans le post' . print_r($_POST, true));
            mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : post erreur', 'Il manque des valeur dans le post' . print_r($_POST, true));
        }


        /*
    •	number 
•	customer_code
•	civility
•	firstname
•	lastname 
•	email 
•	cell_phone 
•	address_1 
•	address_2
•	postal_code 
•	city
•	country 
•	company_name
•	registration_number
•	address_2
•	phone
•	job_title
•	birthdate
•	birthplace 
•	vendor_email 
•	id : id du contrat signé
•	mode : mode de signature
•	pdf : le fichier pdf de upload
•	date : date de signature*/
    }
} else {
    mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Erreur token', 'IP:' . $_SERVER['REMOTE_ADDR'] . "\r\nGET : \r\n" . print_r($_GET, true) . "\r\nPOST:\r\n" . print_r($_POST, true));
    $log->error('Erreur token', 'IP:' . $_SERVER['REMOTE_ADDR'] . "\r\nGET : \r\n" . print_r($_GET, true) . "\r\nPOST:\r\n" . print_r($_POST, true));
}


//test si fichier existe
if (file_exists($temp_pdf))
    @unlink($temp_pdf);
