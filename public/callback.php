<?php
include_once(__DIR__ . '/../src/load.php');

function InsMysql($val,$type='s')
{
    $val  = trim($val);
    if($type == 's')
    {
        return $val ? "'".addslashes($val)."'" : ' NULL ';
    }
    elseif($type == 'i')
    {
        return addslashes($val);
    }
}


file_put_contents('http.log', print_r($_POST,true));
file_put_contents('files.log', print_r($_FILES,true));
file_put_contents('get.log', print_r($_GET,true));

//cutly
$cutly = new cutURL();

//fichier PDF
$temp_pdf = sys_get_temp_dir() . '/' . uniqid('sell') . '.pdf';
//expression reguliere pour devis
$exp_devis = "#Devis n° (D[A-Z]{1,5}[0-9]{5}[a-z]?)#";
//expression reguliere pour acompte
$exp_acompte = "#Acompte demandé : ([0-9]{1,5},[0-9]{2}) €#";



//test si token passer en param
if ($_GET['token'] === $_SERVER['APP_TOKEN']) {


    if (!isset($_FILES['pdf'])) {
        $log->error('Pas de fichier PDF');
        mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Erreur fichier', 'Pas de fichier Pdf');
    }
    elseif ($_FILES['pdf']['type'] != 'application/pdf') {
        $log->error('Pas de fichier PDF');
        mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Erreur fichier', 'le fichier est pas un PDF : ' . print_r($_FILES['pdf'], true));
    }
else {


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



                //recherche de acompte
                if (preg_match($exp_acompte, $p1->getText(), $tab_acompte)) {
                    $mt_acompte = explode(',', $tab_acompte[1]);
                    //url de generation du paiuement
                    $url_paiement = $_SERVER['CB_URL'] . '?total=' . $mt_acompte[0] . $mt_acompte[1] . '&email=' . urlencode($_POST['email']) . '&ref_paiment=' . urlencode($num_devis);
                    $tinyurl = $cutly->cut($url_paiement);

                    $url_mail = $tinyurl['status'] == 7 && $tinyurl['shortLink'] ? $tinyurl['shortLink'] : $url_paiement;






                    //recup email de notif
                    $const_email_notif = 'SELL_SIGN_NOTIF_'.trim(strtoupper($_POST['societe']));
                    //test si on trouve email sin email par defaut
                    $adr_email_notif_user = isset($_SERVER[$const_email_notif])  ? $_SERVER[$const_email_notif] : $_SERVER['SELL_SIGN_NOTIF'] ;
                    //envoie du mail au commercial et email programmé
                    mail($_POST['vendor_email'].','.$adr_email_notif_user,
                        'Signature '.$num_devis.' - '.$_POST['firstname'].' '.$_POST['lastname'],
                        'Voir le devis '.$num_devis.' de '.$_POST['firstname'].' '.$_POST['lastname'].' ('.$_POST['societe'].' - '.$_POST['customer_code'].') : '.$_SERVER['SELL_SEIGN_SHOW_PDF'].$_POST['id']);



                    //rech client
                    $sql_rech = "SELECT s.iban,s.iban_bic, c.id_client,c.code_client,c.societe,a.dtdoc,c.nom,c.prenom,c.adr1,c.adr2,c.codpos,c.mel,c.ville,c.gsm,c.tel
                                    FROM `pro_affaire` as a JOIN pro_client AS c ON c.id_client = a.client_coordonnees_id
                                    LEFT JOIN intra_soc AS s ON s.code_soc = a.societe
                                    
                                    
                                   WHERE a.numdoc = '".addslashes($num_devis)."' OR a.num_devis = '".addslashes($num_devis)."'  ORDER BY a.ddc DESC LIMIT 0,1";
                    $req_rech = mysqli_query($mysql,$sql_rech);
                    $num_rech = mysqli_num_rows($req_rech);

                    $id_ged = ' NULL ';
                    $diff_client = '';
                    $id_client = ' NULL ';
                    $txt_iban = '';
                    //si on trouve un resulta en db
                    if($num_rech)
                    {

                        $r_rech = mysqli_fetch_object($req_rech);
                        $id_client = $r_rech->id_client;

                        $txt_iban = "\n\nSinon par virement\nNumero de devis (Libellé du virement) : $num_devis \nIBAN: ".$r_rech->iban."\nBIC: ".$r_rech->iban_bic;

                        //insert en ged
                        $sql_ins_ged = "INSERT INTO ged2 SET dt_ged_ins = NOW(),actif = 1,ok_index=1,dt_doc=NOW(),dt_scan=NOW(),courier=0,id_user_ins = 1";
                        $req_ins_ged = mysqli_query($mysql,$sql_ins_ged);
                        $id_ged = mysqli_insert_id($mysql);

                        //insert en doc
                        $sql_ins_doc = "INSERT INTO ged2_doc SET id_ged = $id_ged , orientation = 'por'";
                        $req_ins_doc = mysqli_query($mysql,$sql_ins_doc);
                        $id_doc = mysqli_insert_id($mysql);

                        //insert index
                        $sql_ins_index = "INSERT INTO ged2_index SET id_ged = $id_ged ,id_type_doc = 4, dt_index_ins = NOW() , user_index_ins = 1, soc = '".addslashes($r_rech->societe)."',
                        index_ok = 1,cocli = '".addslashes($r_rech->code_client)."', histo_devis = '".addslashes($num_devis)."',num_affaire_global = '".addslashes($num_devis)."',num_devis = '".addslashes($num_devis)."'";
                        $req_ins_index = mysqli_query($mysql,$sql_ins_index);
                        $id_index = mysqli_insert_id($mysql);



                        //copy du fichier en GED
                        $pdf_ged = $_SERVER['GED_BARCODE'].'/Fin/GED'.$id_ged;
                        file_put_contents($pdf_ged.'.txt',$pdf->getText());
                        copy($temp_pdf,$pdf_ged.'.pdf');


                        //test des diff entre
                        if(trim(strtolower($_POST['firstname'])) != trim(strtolower($r_rech->prenom)))
                            $diff_client .= 'prenom,';
                        if(trim(strtolower($_POST['lastname'])) != trim(strtolower($r_rech->nom)))
                            $diff_client .= 'nom,';
                        if(trim(strtolower($_POST['email'])) != trim(strtolower($r_rech->mel)))
                            $diff_client .= 'mel,';
                        if(trim(strtolower($_POST['cell_phone'])) != trim(strtolower($r_rech->gsm)))
                            $diff_client .= 'gsm,';
                        if(trim(strtolower($_POST['address_1'])) != trim(strtolower($r_rech->adr1)))
                            $diff_client .= 'adr1,';
                        if(trim(strtolower($_POST['address_2'])) != trim(strtolower($r_rech->adr2)))
                            $diff_client .= 'adr2,';
                        if(trim(strtolower($_POST['postal_code'])) != trim(strtolower($r_rech->codpos)))
                            $diff_client .= 'codpos,';
                        if(trim(strtolower($_POST['city'])) != trim(strtolower($r_rech->ville)))
                            $diff_client .= 'ville,';
                        if(trim(strtolower($_POST['phone'])) != trim(strtolower($r_rech->tel)))
                            $diff_client .= 'tel,';

                        //supression du dernier carareteres et ajout dans le log
                        if($diff_client)
                        {
                            $diff_client =  substr($diff_client,0,-1) ;
                            $log->warning('Différence dans les champ : '.$diff_client);
                        }

                    }
                    else
                    {
                        //envoie d'un mail erreu car client non trouvé
                        mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Reche du devis', 'Impossible de trouver le devis '.$num_devis);
                        $log->error('Impossible de trouver le devis '.$num_devis);
                    }

                    $sql_ins_gen_upate = " `number` = ".InsMysql($_POST['number']).",
                `customer_code` = ".InsMysql($_POST['customer_code']).",
                `firstname` = ".InsMysql($_POST['firstname']).",
                `lastname` = ".InsMysql($_POST['lastname']).",
                `email` = ".InsMysql($_POST['email']).",
                `cell_phone` = ".InsMysql($_POST['cell_phone']).",
                `address_1` = ".InsMysql($_POST['address_1']).",
                `address_2` = ".InsMysql($_POST['address_2']).",
                `postal_code` = ".InsMysql($_POST['postal_code']).",
                `city` = ".InsMysql($_POST['city']).",
                `date` = ".InsMysql($_POST['date']).",
                `country` = ".InsMysql($_POST['country']).",
                `company_name` = ".InsMysql($_POST['company_name']).",
                `registration_number` = ".InsMysql($_POST['registration_number']).",
                `phone` = ".InsMysql($_POST['phone']).",
                `job_title` = ".InsMysql($_POST['job_title']).",
                `birthdate` = ".InsMysql($_POST['birthdate']).",
                `birthplace` = ".InsMysql($_POST['birthplace']).",
                `vendor_email` = ".InsMysql($_POST['vendor_email']).",
                `date` = ".InsMysql($_POST['date']).",
                `mode` = ".InsMysql($_POST['mode']).",
                `societe` = ".InsMysql($_POST['societe']).",
                `acompte` = ".InsMysql($mt_acompte[0] . '.' . $mt_acompte[1]).",
                `num_devis` = ".InsMysql($num_devis).",
                `id_ged` = ".$id_ged.",
                `diff_client` = ".InsMysql($diff_client).",
                `id_client` = ".$id_client.",
                `url_cb` = ".InsMysql($url_mail);

                    /** INSERT EN DB */
                    $sql_ins = "INSERT INTO sell_sign_pdf SET 
                `id` = ".InsMysql($_POST['id'],'i').",
                $sql_ins_gen_upate
                ON DUPLICATE KEY UPDATE
                $sql_ins_gen_upate
                ";

                    //ajout en db
                    mysqli_query($mysql,$sql_ins);

                    file_put_contents('sqlins.txt',$sql_ins);

                    //envoie d'un sms
                    $txt_sms = "DIRUY :\nPour valider votre comande, merci de payer votre acompte de ".$mt_acompte[0].",".$mt_acompte[1]." euro sur :\n".$url_mail;
                    if(strlen($txt_sms)<=150) {
                        $sql_ins_sms = "INSERT INTO smsd.outbox SET 
              DestinationNumber = '+33" . substr($_POST['cell_phone'], 1) . "',
              TextDecoded = '" . addslashes($txt_sms) . "',
              CreatorID = 'Program',
              Coding = 'Default_No_Compression'";
                        mysqli_query($mysql, $sql_ins_sms);
                    }





                    $email = new Swift_Message('Diruy : Payer Votre acompte');
                    $email->setFrom([$_SERVER['MAILER_EMAIL_DEVIS_EXP']=>$_SERVER['MAILER_EMAIL_DEVIS_NOM']])
                        ->setTo($_POST['email'])
                        ->setBody('Madame, Monsieur,
                        Merci de votre confiance.
                        Votre commande sera définitivement enregistrée après règlement de l’acompte dû soit ' . $mt_acompte[0] . ',' . $mt_acompte[1] . '&euro;.<br>
                        Afin de procéder au paiement en ligne, merci de vous rendre sur : ' . $url_mail.$txt_iban )
                        ->addPart('Madame, Monsieur,<br>
                    Merci de votre confiance.<br>
                    Votre commande sera définitivement enregistrée après règlement de l’acompte dû soit ' . $mt_acompte[0] . ',' . $mt_acompte[1] . '&euro;.<br>
                    <a href="' . $url_mail . '">Afin de procéder au paiement en ligne, merci de cliquer ici.</a>'.nl2br($txt_iban),'text/html');
                    $mailer->send($email);
                } else {
                    mail($_SERVER['MAILER_EMAIL_ADMIN'], 'Sell&Sign : Erreur fichier', 'Pas de numero montant acompte .....');
                    $log->error('Pas de numero montant acompte .....');
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
