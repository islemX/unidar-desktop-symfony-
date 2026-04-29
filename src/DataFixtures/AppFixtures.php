<?php

namespace App\DataFixtures;

use App\Entity\ContractTemplate;
use App\Entity\Faculty;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->loadAdmin($manager);
        $this->loadFaculties($manager);
        $this->loadContractTemplates($manager);

        $manager->flush();
    }

    private function loadAdmin(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@unidar.com');
        $admin->setFullName('UNIDAR Admin');
        $admin->setRole(UserRole::Admin);
        $admin->setStatus(UserStatus::Active);
        $admin->setPassword($this->hasher->hashPassword($admin, 'Admin@2024!'));

        $manager->persist($admin);
    }

    private function loadFaculties(ObjectManager $manager): void
    {
        $faculties = [
            ['Faculté des Sciences de Tunis', 'Tunis', 'Tunis', '2236 El Manar, Tunis', 36.8415, 10.1739],
            ['Faculté des Sciences Économiques et de Gestion de Tunis', 'Tunis', 'Tunis', 'Campus Universitaire, El Manar', 36.8440, 10.1711],
            ['Faculté de Médecine de Tunis', 'Tunis', 'Tunis', 'Rue Djebel Lakhdar, La Rabta', 36.8192, 10.1731],
            ['École Nationale d\'Ingénieurs de Tunis (ENIT)', 'Tunis', 'Tunis', 'BP 37, Le Belvédère', 36.8358, 10.1531],
            ['Institut Supérieur de Gestion de Tunis', 'Tunis', 'Tunis', '41 Rue de la Liberté, Bouchoucha', 36.8208, 10.1778],
            ['Faculté des Lettres, des Arts et des Humanités de la Manouba', 'La Manouba', 'Manouba', 'Campus Universitaire, Manouba', 36.8100, 10.0981],
            ['École Nationale des Sciences de l\'Informatique (ENSI)', 'La Manouba', 'Manouba', 'Campus Universitaire, Manouba', 36.8117, 10.0972],
            ['Institut Supérieur des Arts Multimédia de la Manouba', 'La Manouba', 'Manouba', 'Campus Universitaire, Manouba', 36.8122, 10.0986],
            ['Faculté des Sciences de Bizerte', 'Bizerte', 'Bizerte', 'Jarzouna, Bizerte', 37.2750, 9.8650],
            ['Institut Supérieur des Sciences Appliquées et de Technologie de Bizerte', 'Bizerte', 'Bizerte', 'Route de Menzel Abderrahman, Bizerte', 37.2744, 9.8678],
            ['Faculté des Sciences de Sfax', 'Sfax', 'Sfax', 'Route de Soukra, Sfax', 34.7561, 10.7600],
            ['Faculté de Médecine de Sfax', 'Sfax', 'Sfax', 'Avenue Majida Boulila, Sfax', 34.7406, 10.7600],
            ['École Nationale d\'Ingénieurs de Sfax (ENIS)', 'Sfax', 'Sfax', 'Route de Soukra Km 3.5, Sfax', 34.7550, 10.7642],
            ['Institut Supérieur de Gestion de Sfax', 'Sfax', 'Sfax', 'Route de l\'Aéroport, Sfax', 34.7500, 10.7597],
            ['Faculté des Sciences Économiques et de Gestion de Sfax', 'Sfax', 'Sfax', 'Route de l\'Aéroport, Sfax', 34.7489, 10.7600],
            ['Faculté des Lettres et Sciences Humaines de Sfax', 'Sfax', 'Sfax', 'Route de l\'Aéroport, Sfax', 34.7500, 10.7550],
            ['Faculté des Sciences de Sousse', 'Sousse', 'Sousse', 'Cité Erriadh, Sousse', 35.8245, 10.6346],
            ['Faculté de Médecine de Sousse', 'Sousse', 'Sousse', 'Mohamed Karoui, Sousse', 35.8231, 10.6350],
            ['École Nationale d\'Ingénieurs de Sousse (ENISo)', 'Sousse', 'Sousse', 'Cité Erriadh, Sousse', 35.8230, 10.6347],
            ['Institut Supérieur de Gestion de Sousse', 'Sousse', 'Sousse', 'Cité Erriadh, Sousse', 35.8220, 10.6350],
            ['Faculté des Sciences Économiques et de Gestion de Sousse', 'Sousse', 'Sousse', 'Cité Erriadh, Sousse', 35.8215, 10.6355],
            ['Faculté des Lettres et Sciences Humaines de Sousse', 'Sousse', 'Sousse', 'Cité Erriadh, Sousse', 35.8210, 10.6358],
            ['Faculté des Sciences de Monastir', 'Monastir', 'Monastir', 'Avenue de l\'Environnement, Monastir', 35.7760, 10.8200],
            ['Faculté de Médecine de Monastir', 'Monastir', 'Monastir', 'Avenue Avicenne, Monastir', 35.7722, 10.8217],
            ['École Nationale d\'Ingénieurs de Monastir (ENIMo)', 'Monastir', 'Monastir', 'Ibn El Jazzar, Skanes', 35.7578, 10.8339],
            ['Institut Supérieur de Biotechnologie de Monastir', 'Monastir', 'Monastir', 'Avenue Tahar Hadded, Monastir', 35.7692, 10.8181],
            ['Faculté des Sciences de Gabès', 'Gabès', 'Gabès', 'Route de Médenine, Zrig, Gabès', 33.8817, 10.1044],
            ['Institut Supérieur des Sciences Appliquées et de Technologie de Gabès', 'Gabès', 'Gabès', 'Route de Médenine, Gabès', 33.8800, 10.1050],
            ['École Nationale d\'Ingénieurs de Gabès (ENIGa)', 'Gabès', 'Gabès', 'Omar Ibn El Khattab, Gabès', 33.8833, 10.0964],
            ['Faculté des Sciences de Gafsa', 'Gafsa', 'Gafsa', 'Sidi Ahmed Zarrouk, Gafsa', 34.4225, 8.7842],
            ['École Nationale d\'Ingénieurs de Gafsa', 'Gafsa', 'Gafsa', 'Sidi Ahmed Zarrouk, Gafsa', 34.4222, 8.7844],
            ['Faculté des Sciences de Jendouba', 'Jendouba', 'Jendouba', 'Campus Universitaire, Jendouba', 36.5011, 8.7803],
            ['Faculté des Sciences de Kairouan', 'Kairouan', 'Kairouan', 'Route de Tunis, Kairouan', 35.6714, 10.0944],
            ['Institut Supérieur des Sciences Appliquées et de Technologie de Kairouan', 'Kairouan', 'Kairouan', 'Route de Tunis, Kairouan', 35.6700, 10.0950],
            ['Faculté des Lettres et Sciences Humaines de Kairouan', 'Kairouan', 'Kairouan', 'Route de Tunis, Kairouan', 35.6700, 10.0958],
            ['Faculté des Sciences Économiques et de Gestion de Mahdia', 'Mahdia', 'Mahdia', 'Cité Hiboun, Mahdia', 35.5178, 11.0350],
            ['Institut Supérieur des Sciences et Technologies de l\'Environnement de Hammam-Lif', 'Hammam-Lif', 'Ben Arous', 'BP 1003, Hammam-Lif', 36.7228, 10.3372],
            ['Faculté des Sciences de Béja', 'Béja', 'Béja', 'Campus Universitaire, Béja', 36.7250, 9.1833],
            ['Faculté des Sciences de Kef', 'Kef', 'Kef', 'Campus Universitaire, Kef', 36.1742, 8.7047],
            ['Faculté des Sciences de Tozeur', 'Tozeur', 'Tozeur', 'Route de Nefta, Tozeur', 33.9197, 8.1336],
            ['Institut Supérieur des Études Technologiques de Radès', 'Radès', 'Ben Arous', 'Route de Soliman, Radès', 36.7742, 10.2747],
            ['Institut Supérieur des Études Technologiques de Tunis', 'Tunis', 'Tunis', 'Rue Taha Hussein, Montfleury', 36.8386, 10.1800],
            ['Institut Supérieur des Études Technologiques de Sfax', 'Sfax', 'Sfax', 'Route de Soukra, Sfax', 34.7533, 10.7625],
            ['Institut Supérieur des Études Technologiques de Sousse', 'Sousse', 'Sousse', 'Cité Erriadh, Sousse', 35.8225, 10.6352],
            ['Institut Supérieur des Études Technologiques de Gabes', 'Gabès', 'Gabès', 'Route de Médenine, Gabès', 33.8820, 10.1046],
            ['Institut Supérieur des Arts et Métiers de Sfax', 'Sfax', 'Sfax', 'Route de Soukra, Sfax', 34.7542, 10.7608],
            ['Institut Supérieur des Arts et Métiers de Gabès', 'Gabès', 'Gabès', 'Route de Médenine, Gabès', 33.8812, 10.1042],
            ['Faculté de Droit et des Sciences Politiques de Tunis', 'Tunis', 'Tunis', 'Boulevard du 9 Avril 1938', 36.8175, 10.1786],
            ['Faculté de Droit et des Sciences Politiques de Sfax', 'Sfax', 'Sfax', 'Route de l\'Aéroport, Sfax', 34.7495, 10.7605],
            ['Faculté de Droit et des Sciences Politiques de Sousse', 'Sousse', 'Sousse', 'Cité Erriadh, Sousse', 35.8218, 10.6362],
            ['Faculté des Sciences Humaines et Sociales de Tunis', 'Tunis', 'Tunis', 'Boulevard 9 Avril 1938, Bab Mnara', 36.8183, 10.1797],
            ['Institut Supérieur de Musique de Tunis', 'Tunis', 'Tunis', '20 Avenue de Paris, Tunis', 36.7972, 10.1797],
            ['Institut National du Sport, de la Jeunesse et de l\'Éducation Physique (INSEEP)', 'Tunis', 'Tunis', 'Ksar Said, Manouba', 36.8072, 10.1000],
        ];

        foreach ($faculties as [$name, $city, $governorate, $address, $lat, $lng]) {
            $faculty = new Faculty();
            $faculty->setName($name);
            $faculty->setCity($city);
            $faculty->setGovernorate($governorate);
            $faculty->setAddress($address);
            $faculty->setLatitude($lat);
            $faculty->setLongitude($lng);
            $manager->persist($faculty);
        }
    }

    private function loadContractTemplates(ObjectManager $manager): void
    {
        $templates = [
            [
                'name' => 'Contrat Standard d\'Hébergement Étudiant',
                'type' => 'standard',
                'content' => $this->getStandardTemplate(),
                'legal' => $this->getStandardLegalClauses(),
                'tunisia' => $this->getTunisiaSpecificClauses(),
            ],
            [
                'name' => 'Contrat Studio Meublé',
                'type' => 'studio',
                'content' => $this->getStudioTemplate(),
                'legal' => $this->getStandardLegalClauses(),
                'tunisia' => $this->getTunisiaSpecificClauses(),
            ],
            [
                'name' => 'Contrat Appartement',
                'type' => 'apartment',
                'content' => $this->getApartmentTemplate(),
                'legal' => $this->getStandardLegalClauses(),
                'tunisia' => $this->getTunisiaSpecificClauses(),
            ],
            [
                'name' => 'Contrat Chambre Partagée',
                'type' => 'shared_room',
                'content' => $this->getSharedRoomTemplate(),
                'legal' => $this->getSharedRoomLegalClauses(),
                'tunisia' => $this->getTunisiaSpecificClauses(),
            ],
        ];

        foreach ($templates as $t) {
            $template = new ContractTemplate();
            $template->setTemplateName($t['name']);
            $template->setTemplateType($t['type']);
            $template->setContractContent($t['content']);
            $template->setLegalClauses($t['legal']);
            $template->setTunisiaSpecificClauses($t['tunisia']);
            $template->setIsActive(true);
            $manager->persist($template);
        }
    }

    private function getStandardTemplate(): string
    {
        return <<<EOT
CONTRAT DE LOCATION À USAGE D'HABITATION

Entre les soussignés :

BAILLEUR : {{owner_name}}
Téléphone : {{owner_phone}}
Email : {{owner_email}}

LOCATAIRE : {{student_name}}
Université : {{student_university}}
Téléphone : {{student_phone}}
Email : {{student_email}}

DÉSIGNATION DU BIEN :
Adresse : {{listing_address}}
Type : {{property_type}}
Nombre de chambres : {{bedrooms}}
Surface approximative : {{surface}} m²

CONDITIONS FINANCIÈRES :
Loyer mensuel : {{monthly_rent}} TND
Dépôt de garantie : {{security_deposit}} TND (équivalent à 1 mois de loyer)
Frais de plateforme UNIDAR (5%) : {{platform_fee}} TND

DURÉE DU CONTRAT :
Date de début : {{start_date}}
Date de fin : {{end_date}}
Durée : {{duration}} mois

OBLIGATIONS DU LOCATAIRE :
1. Payer le loyer avant le 5 de chaque mois
2. Entretenir le logement en bon état
3. Ne pas sous-louer sans accord écrit du bailleur
4. Respecter le règlement intérieur de l'immeuble
5. Souscrire une assurance habitation

OBLIGATIONS DU BAILLEUR :
1. Délivrer le logement en bon état
2. Assurer la jouissance paisible du bien
3. Effectuer les réparations nécessaires
4. Respecter la vie privée du locataire

{{legal_clauses}}

{{tunisia_specific_clauses}}

Fait à Tunis, le {{contract_date}}

SIGNATURE DU BAILLEUR :                    SIGNATURE DU LOCATAIRE :

{{owner_signature_placeholder}}            {{student_signature_placeholder}}
EOT;
    }

    private function getStudioTemplate(): string
    {
        return <<<EOT
CONTRAT DE LOCATION - STUDIO MEUBLÉ

BAILLEUR : {{owner_name}} | Tél : {{owner_phone}}
LOCATAIRE : {{student_name}} | Université : {{student_university}}

BIEN LOUÉ :
Studio meublé situé au : {{listing_address}}

Le studio comprend : espace de vie/chambre, coin cuisine équipé, salle de bain.
Meublé avec : lit, bureau, armoire, table, chaises, réfrigérateur, plaques de cuisson.

LOYER : {{monthly_rent}} TND/mois (charges comprises)
DÉPÔT DE GARANTIE : {{security_deposit}} TND

DURÉE : du {{start_date}} au {{end_date}}

{{legal_clauses}}
{{tunisia_specific_clauses}}

Fait à Tunis, le {{contract_date}}

Bailleur : {{owner_signature_placeholder}}
Locataire : {{student_signature_placeholder}}
EOT;
    }

    private function getApartmentTemplate(): string
    {
        return <<<EOT
CONTRAT DE LOCATION D'APPARTEMENT

PARTIES :
Bailleur : {{owner_name}}, Tél : {{owner_phone}}
Locataire : {{student_name}}, Étudiant(e) à {{student_university}}

BIEN :
Appartement de {{bedrooms}} chambre(s) situé au {{listing_address}}
Loyer mensuel : {{monthly_rent}} TND
Charges : eau et électricité en sus
Dépôt de garantie : {{security_deposit}} TND

PÉRIODE : {{start_date}} → {{end_date}} ({{duration}} mois)

Le locataire s'engage à maintenir l'appartement propre et à signaler toute dégradation
dans les 48h. Tout dommage causé par négligence sera déduit du dépôt de garantie.

{{legal_clauses}}
{{tunisia_specific_clauses}}

Signatures :
Bailleur : {{owner_signature_placeholder}}
Locataire : {{student_signature_placeholder}}
Date : {{contract_date}}
EOT;
    }

    private function getSharedRoomTemplate(): string
    {
        return <<<EOT
CONTRAT DE COLOCATION ÉTUDIANTE

BAILLEUR : {{owner_name}}
COLOCATAIRE : {{student_name}} — {{student_university}}

LOGEMENT : {{listing_address}}
CHAMBRE : Chambre individuelle dans logement partagé
PARTIES COMMUNES : Cuisine, salon, salle de bain (partagés)

LOYER : {{monthly_rent}} TND/mois (votre part)
DÉPÔT : {{security_deposit}} TND

RÈGLES DE VIE EN COMMUNAUTÉ :
1. Respect des espaces communs
2. Rotation du ménage des parties communes
3. Silence après 23h en semaine
4. Visiteurs acceptés jusqu'à 22h
5. Pas de fumée à l'intérieur

DURÉE : {{start_date}} au {{end_date}}

{{legal_clauses}}
{{tunisia_specific_clauses}}

Bailleur : {{owner_signature_placeholder}}        Colocataire : {{student_signature_placeholder}}
Le : {{contract_date}}
EOT;
    }

    private function getStandardLegalClauses(): string
    {
        return <<<EOT
CLAUSES LÉGALES :

Article 1 - Résiliation anticipée :
Toute résiliation anticipée doit être notifiée par écrit avec un préavis d'un mois. Le dépôt
de garantie sera restitué dans un délai de 30 jours après l'état des lieux de sortie.

Article 2 - Révision du loyer :
Le loyer ne peut être révisé qu'une fois par an, dans la limite de l'indice des prix à
la consommation publié par l'INS (Institut National de la Statistique).

Article 3 - Sous-location :
Toute sous-location, même partielle, est strictement interdite sans accord écrit préalable
du bailleur sous peine de résiliation immédiate du contrat.

Article 4 - Dégradations :
Le locataire est responsable de toute dégradation survenue pendant la durée du contrat,
à l'exception de l'usure normale.

Article 5 - Accès du bailleur :
Le bailleur ne peut accéder au logement sans accord préalable du locataire, sauf en cas
d'urgence, avec un préavis de 24 heures minimum.
EOT;
    }

    private function getSharedRoomLegalClauses(): string
    {
        return <<<EOT
CLAUSES LÉGALES COLOCATION :

Article 1 - Solidarité :
Chaque colocataire est responsable de sa quote-part du loyer. En cas de départ d'un
colocataire, le bailleur doit être informé 30 jours à l'avance.

Article 2 - Remplacement :
Le remplacement d'un colocataire est soumis à l'approbation écrite du bailleur et des
autres colocataires.

Article 3 - Dépôt de garantie :
Le dépôt de garantie est individuel et restitué après état des lieux de la chambre et
des parties communes.
EOT;
    }

    private function getTunisiaSpecificClauses(): string
    {
        return <<<EOT
DISPOSITIONS SPÉCIFIQUES (DROIT TUNISIEN) :

Conformément au Code des Obligations et des Contrats (COC) tunisien :

1. Ce contrat est régi par les articles 728 à 756 du COC relatifs au louage de choses.

2. En cas de litige, les parties s'engagent à recourir en premier lieu à une médiation
   amiable avant toute procédure judiciaire.

3. La juridiction compétente est le Tribunal de Première Instance du lieu de situation
   de l'immeuble.

4. Conformément à la législation tunisienne, le bailleur s'engage à déclarer les revenus
   locatifs auprès des autorités fiscales compétentes.

5. Le locataire est tenu de présenter sa carte d'étudiant valide et une pièce d'identité
   nationale (CIN) ou passeport lors de la signature du contrat.

6. Tout contrat de location d'une durée supérieure à 3 mois doit être enregistré auprès
   du bureau de contrôle des impôts compétent (frais d'enregistrement à la charge du bailleur).

Plateforme UNIDAR - Service de mise en relation étudiant/bailleur.
Commission de service : 5% du premier mois de loyer, prélevée via la plateforme.
EOT;
    }
}
