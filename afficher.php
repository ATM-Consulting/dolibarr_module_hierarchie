<?php

require('config.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';


$langs->load("companies");
$langs->load("products");
$langs->load("admin");
$langs->load("users");
$langs->load("languages");


// Defini si peux lire/modifier permisssions
$canreaduser=($user->admin || $user->rights->user->user->lire);

$id = GETPOST('id','int');
$action = GETPOST('action','alpha');

if ($id)
{
    // $user est le user qui edite, $id est l'id de l'utilisateur edite
    $caneditfield=((($user->id == $id) && $user->rights->user->self->creer)
    || (($user->id != $id) && $user->rights->user->user->creer));
}

// Security check
$socid=0;
if ($user->societe_id > 0) $socid = $user->societe_id;
$feature2 = (($socid && $user->rights->user->self->creer)?'':'user');
if ($user->id == $id)	// A user can always read its own card
{
    $feature2='';
    $canreaduser=1;
}
$result = restrictedArea($user, 'user', $id, '&user', $feature2);
if ($user->id <> $id && ! $canreaduser) accessforbidden();


// Charge utilisateur edite
$fuser = new User($db);
$fuser->fetch($id);
$fuser->getrights();

$form = new Form($db);

$arret=0;

$ATMdb=new TPDOdb;

llxHeader('', '', '', '', 0, 0, array('/hierarchie/js/jquery.jOrgChart.js'));


?>
<link rel="stylesheet" type="text/css" href="./css/jquery.jOrgChart.css" />
<?


$head = user_prepare_head($fuser);
$current_head = 'hierarchie';
dol_fiche_head($head, $current_head, $langs->trans('Utilisateur'),0, 'user');

?>
<script>
    jQuery(document).ready(function() {
    	
    	$("#JQorganigramme").jOrgChart({
            chartElement : '#chart',
            dragAndDrop : false
        });
    });
    </script>

<?
global $user;

$orgChoisie=__get("choixAffichage", 'equipe','string',30);
$idUserCourant=__get('id',0,'integer');

//////////////////////////////////////récupération des informations de l'utilisateur courant
	$sqlReqUser="SELECT * FROM `".MAIN_DB_PREFIX."user` where rowid=".$idUserCourant;
	$ATMdb->Execute($sqlReqUser);
	$Tab=array();
	$ATMdb->Get_line();
	$userCourant=new User($db);
	$userCourant->id=$ATMdb->Get_field('rowid');
	$userCourant->lastname=$ATMdb->Get_field('lastname');
	$userCourant->firstname=$ATMdb->Get_field('firstname');
	$userCourant->fk_user=$ATMdb->Get_field('fk_user');
	$Tab[]=$userCourant;
				

//print "salut".$userCourant->rowid.$userCourant->lastname.$userCourant->firstname.$userCourant->fk_user;




//Fonction qui permet d'afficher les utilisateurs qui sont en dessous hiérarchiquement du salarié passé en paramètre
function afficherSalarieDessous(&$ATMdb, $idBoss = -1, $niveau=1){
		
				global $user, $db, $idUserCourant, $userCourant, $conf;
				?>
				<ul id="ul-niveau-<?=$niveau ?>">
				<?
				
				$sqlReq="SELECT rowid FROM `".MAIN_DB_PREFIX."user` WHERE entity IN (0,".(! empty($conf->multicompany->enabled) && ! empty($conf->multicompany->transverse_mode)?"1,":"").$conf->entity.")";
				if($idBoss>0)$sqlReq.= " AND fk_user=".$idBoss;
				else $sqlReq.=" AND fk_user IS NULL ";

				$ATMdb->Execute($sqlReq);

				$Tab=array();
				while($ATMdb->Get_line()) {
					$user=new User($db);
					$user->fetch();
					
					$Tab[]=$ATMdb->Get_field('rowid');
				}
				
				foreach($Tab as $userid) {
					?>
					<li class="utilisateur" rel="<?=$userid ?>"><?
					afficherSalarie($ATMdb, $userid);
					afficherSalarieDessous($ATMdb, $userid,$niveau+1);
					?></li><?
				}
				?></ul><?		
}

//Fonction qui permet d'afficher un salarié
function afficherSalarie(&$ATMdb, $idUser){
				
				global $user, $db, $idUserCourant, $userCourant;

				$user=new User($db);
				$user->fetch($idUser);
 
					?>
				<a href="<?=DOL_URL_ROOT ?>/user/fiche.php?id=<?=$user->id ?>"><?=$user->firstname." ".$user->lastname ?></a>
				<? if(!empty($user->office_phone) || !empty($user->user_mobile)) { ?><div class="tel">Tél. : <?=$user->office_phone.' '.$user->user_mobile ?></div><? }
				if(!empty($user->email) ) { ?><div class="mail">Email : <a href="mailto:<?=$user->email ?>"><?=$user->email ?></a></div><? }
				if(!empty($user->job) ) { ?><div><?=$user->job ?></div><? }
			
				?><?
		
}

//Fonction qui permet d'afficher un salarié
function afficherGroupeSousValideur(&$ATMdb, $idUser, $fkusergroup, $niveau=1){
		
				global $user, $db, $idUserCourant, $userCourant;

				$sqlReq=" SELECT  DISTINCT u.fk_user 
				FROM ".MAIN_DB_PREFIX."usergroup_user as u 
				WHERE u.fk_usergroup=".$fkusergroup." 
				AND  u.fk_user NOT IN(SELECT v.fk_user FROM ".MAIN_DB_PREFIX."usergroup_user as v WHERE v.fk_user=".$idUser.")";
				
				$ATMdb->Execute($sqlReq);
				
				$Tab=array();
				while($ATMdb->Get_line()) {
					$user=new User($db);
					$user->fetch($ATMdb->Get_field('fk_user'));
					
					$Tab[]=$user;
				}
				print "<ul>";
				foreach($Tab as &$user) {
					
					?>
					<li class="utilisateur" rel="<?=$user->id ?>">
						<a href="<?=DOL_URL_ROOT ?>/user/fiche.php?id=<?=$user->id ?>"><?=$user->firstname." ".$user->lastname ?></a>
						<? if(!empty($user->office_phone) || !empty($user->user_mobile)) { ?><div class="tel">Tél. : <?=$user->office_phone.' '.$user->user_mobile ?></div><? }
						if(!empty($user->email) ) { ?><div class="mail">Email : <a href="mailto:<?=$user->email ?>"><?=$user->email ?></div><? }
					
					?><?
				}
				print "</ul>";
				
				?><?
}


//Fonction qui permet d'afficher les groupes dans la liste déroulante 
function afficherGroupes(&$ATMdb){
				global $user, $db, $idUserCourant, $userCourant;
				//récupère les id des différents groupes de l'utilisateur
				$sqlReq="SELECT g.nom
					 FROM `".MAIN_DB_PREFIX."usergroup_user` ug LEFT JOIN ".MAIN_DB_PREFIX."usergroup g ON (g.rowid=ug.fk_usergroup)
					WHERE ug.fk_user=".$userCourant->id;
				$ATMdb->Execute($sqlReq);
				while($ATMdb->Get_line()) {
						//affichage des groupes concernant l'utilisateur 
						print '<option value="'.$ATMdb->Get_field('nom').'">'.$ATMdb->Get_field('nom').'</option>';
				}
}

function findFkUserGroup(&$ATMdb, $nomGroupe){
	$sqlFkGroupe="SELECT rowid 
	FROM ".MAIN_DB_PREFIX."usergroup
	WHERE nom='".addslashes($nomGroupe)."'";
	
	$ATMdb->Execute($sqlFkGroupe);
	$ATMdb->Get_line();
	return $ATMdb->Get_field('rowid');
	
}

function findIdValideur(&$ATMdb, $fkusergroup){

	$sqlidValideur="SELECT fk_user FROM ".MAIN_DB_PREFIX."rh_valideur_groupe WHERE fk_usergroup=".$fkusergroup;
	$ATMdb->Execute($sqlidValideur);
	$Tab=array();
	$nbValideur=0;
	while($ATMdb->Get_line()) {
			//return $ATMdb->Get_field('fk_user');
			//$idValideurGroupe=findIdValideur($ATMdb,$fkusergroup);
			$Tab[]=$ATMdb->Get_field('fk_user');
			$nbValideur++;
	}

	print '<ul id="ul-niveau-1">';
	
	
	if($nbValideur>0){
		foreach($Tab as $fkuser){
		print '<li class="utilisateur" rel="'.$fkuser.'">';
		
		afficherSalarie($ATMdb,$fkuser);
		//afficherSalarieDessous($ATMdb,$fkuser,1);
		afficherGroupeSousValideur($ATMdb,$fkuser,$fkusergroup,1);
		// SELECT  u.rowid FROM ".MAIN_DB_PREFIX."user as u WHERE u.rowid NOT IN (SELECT g.fk_user FROM ".MAIN_DB_PREFIX."rh_valideur_groupe as g WHERE g.fk_usergroup=2)
		print '</li>';
		}
		print '</ul>';
	}
	else{
		
		$sql="SELECT fk_user  FROM ".MAIN_DB_PREFIX."usergroup_user WHERE fk_usergroup=".$fkusergroup;
		$ATMdb->Execute($sql);
		
		while($ATMdb->Get_line()){
			$TUserG[]=$ATMdb->Get_field('fk_user');
		}
		foreach($TUserG as $id){
			print '<li class="utilisateur" rel="'.$id.'">';
			afficherSalarie($ATMdb,$id);
			print '</li>';
		}
		print '</ul>';
	}
	
}

function afficherUtilisateurGroupe(&$ATMdb, $nomGroupe){
			echo $nomGroupe;
			$fkusergroup=findFkUserGroup($ATMdb, $nomGroupe);	
			$idValideurGroupe=findIdValideur($ATMdb,$fkusergroup);

			afficherSalarieDessous($ATMdb,$idValideurGroupe, 1);
}

?>


<form id="form" action="afficher.php?id=<?= $userCourant->id; ?>" method="get">
	<select id="choixAffichage" name="choixAffichage">
		<option value="entreprise">Afficher la hiérarchie de l'entreprise</option>
		<option value="equipe">Afficher son équipe</option>
		<?php
			afficherGroupes($ATMdb);
		?>
	</select> 
	<input  name="id" value="<?=$_REQUEST['id'] ?>" type="hidden" />
	<input id="validSelect" type="submit" value="Valider" class="button" />
</form>

<?php


if($orgChoisie=="entreprise"){	//on affiche l'organigramme de l'entreprise 
///////////////////////////////ORGANIGRAMME ENTREPRISE


	$socName = empty($conf->global->MAIN_INFO_SOCIETE_LOGO_MINI) ? $conf->global->MAIN_INFO_SOCIETE_NOM : '<img src="'.DOL_URL_ROOT.'/viewimage.php?cache=1&amp;modulepart=companylogo&amp;file='.urlencode('thumbs/'.$conf->global->MAIN_INFO_SOCIETE_LOGO_MINI).'" />';
	//print_r($conf->global);

	$socName = empty($conf->global->MAIN_INFO_SOCIETE_LOGO) ? $conf->global->MAIN_INFO_SOCIETE_NOM : '<img src="'.DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=companylogo&file='.urlencode($conf->global->MAIN_INFO_SOCIETE_LOGO).'" />';
//	print_r($conf->global);


?>
	<div id="organigrammePrincipal">
		<h2>Hiérarchie de l'entreprise</h2>
		<div id="chart" class="orgChart" align="center"></div>
		
		<ul id="JQorganigramme" style="display:none;">
			<li><?=$socName ?>
		<?php 		
			$ATMdb=new TPDOdb;
			afficherSalarieDessous($ATMdb);
			$ATMdb->close();
		?>
			</li>
		</ul>
	</div>
	
	
	
<?php
}else if($orgChoisie=="equipe"){	//on affiche l'organigramme de l'équipe
?>
	<div id="organigrammeEquipe">
		<h2>Hiérarchie de votre équipe</h2>
		<div id="chart" class="orgChart" align="center"></div>
		
		<ul id="JQorganigramme" style="display:none;">
			<li>Votre Equipe
		<?php 		
			$ATMdb=new TPDOdb;
			if($userCourant->fk_user!="0"){		// si on a un supérieur hiérarchique, on affiche son nom, puis l'équipe 
			
				$sqlReq="SELECT name,firstname FROM `".MAIN_DB_PREFIX."user` where rowid=".$userCourant->fk_user;
				$ATMdb->Execute($sqlReq);
				$Tab=array();
				while($ATMdb->Get_line()) {
					//récupère les id des différents nom des  groupes de l'utilisateur
					
					print '<ul><li>'.$ATMdb->Get_field('firstname')." ".$ATMdb->Get_field('lastname')/*."<br/>(Votre supérieur)"*/;
					
				}
				afficherSalarieDessous($ATMdb,$userCourant->fk_user);
				
			}else {		// si on n'a pas de supérieur, on écrit son nom, puis ceux de ses collaborateurs inférieurs
						
					print '<ul><li>'.$userCourant->firstname." ".$userCourant->lastname."<br/>(Vous-même)";
					afficherSalarieDessous($ATMdb,$userCourant->id, 1);
					print "</li></ul>";
				
			}
			
			$ATMdb->close();
		?>
			</li>
		</ul>
	</div>
	
	
	
	
	
<?php 
}else{	//on affiche l'organigramme du groupe  
?>	
	<div id="organigrammeGroupe">
		<h1>Hiérarchie du groupe</h1>
		<div id="chart" class="orgChart"></div>
		
		<ul id="JQorganigramme" style="display:none;">
			<li> 
		<?php 	
			$ATMdb=new TPDOdb;
			//on affiche les utilisateurs du groupe en cours
		 	afficherUtilisateurGroupe($ATMdb,$orgChoisie);
			$ATMdb->close();
		?>
			</li>
		</ul>
	</div>
<?php	
}



?>
<script>	
	$(document).ready( function(){
		$("#choixAffichage option[value='<?= $orgChoisie?>']").attr('selected', 'selected');
		 <?php 
		 	if($orgChoisie==""){?>
		 		$('#organigrammeGroupe').hide();
		 	<?php }
		 ?>
	});
</script>


<?php

dol_fiche_end();

llxFooter();
$db->close();

