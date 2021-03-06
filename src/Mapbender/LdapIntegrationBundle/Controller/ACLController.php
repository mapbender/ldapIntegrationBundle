<?php

namespace Mapbender\LdapIntegrationBundle\Controller;

use FOM\UserBundle\Controller\ACLController as BaseACLController;
use FOM\ManagerBundle\Configuration\Route;
use FOM\UserBundle\Entity\Group;
use FOM\UserBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class ACLController extends BaseACLController
{

    /**
     * @Route("/acl/search/{slug}", name="fom_user_acl_search")
     * @Method({ "GET" })
     * @Template("MapbenderLdapIntegrationBundle:ACL:ldap-result.html.twig")
     */
    public function searchAction($slug)
    {
        /** @var User[] $dbUsers */
        /** @var Group[] $groups */
        $container  = $this->container;
        $idProvider = $this->get('fom.identities.provider');
        $groups     = $idProvider->getAllGroups();
        $dbUsers    = $idProvider->getAllUsers();
        $users      = array();

        foreach ($dbUsers as $tmpUser) {
            if (is_object($tmpUser) && get_class($tmpUser) !== "stdClass" && strpos($tmpUser->getUsername(), $slug) !== false) {
                $users[] = $tmpUser;
            }
        }

        //**//
        // Settings for LDAP
        $ldapHostname      = $container->getParameter("ldap_host");
        $ldapPort          = $container->getParameter("ldap_port");
        $ldapVersion       = $container->getParameter("ldap_version");
        $baseDn            = $container->getParameter("ldap_user_base_dn");
        $roleBaseDn        = $container->getParameter("ldap_role_base_dn");
        $roleNameAttribute = $container->getParameter("ldap_role_name_attribute");
        $nameAttribute     = $container->getParameter("ldap_user_name_attribute");
        $bindDn            = $container->getParameter("ldap_bind_dn");
        $bindPasswd        = $container->getParameter("ldap_bind_pwd");

        $connection = @ldap_connect($ldapHostname, $ldapPort);
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $ldapVersion);

        if (strlen($bindDn) !== 0 && strlen($bindPasswd) !== 0) {
            if (!ldap_bind($connection, $bindDn, $bindPasswd)) {
                throw new \Exception('Unable to bind LDAP to DN: ' . $bindDn);
            }

        }

        // Add Users from LDAP
        $filter = "(" . $nameAttribute . "=*" . $slug . "*)";

        $ldapListRequest = ldap_search($connection, $baseDn, $filter);

        if (!$ldapListRequest) {
            throw new \Exception('Unable to search in LDAP. LdapError: ' . ldap_error($connection));
        }
        $ldapUserList = ldap_get_entries($connection, $ldapListRequest);


        foreach ($ldapUserList as $ldapUser) {
            if (gettype($ldapUser) === 'array') { // first entry is the number of results!
                $user              = new \stdClass;
                $user->getUsername = $ldapUser[$nameAttribute][0];
                $users[]           = $user;
            }
        }

        // Add Groups from LDAP
        $filter = "(" . $roleNameAttribute . "=*" . $slug . "*)";

        $ldapListRequest = ldap_search($connection, $roleBaseDn, $filter);

        if (!$ldapListRequest) {
            throw new \Exception('Unable to search in LDAP. LdapError: ' . ldap_error($connection));
        }
        $ldapGroupList = ldap_get_entries($connection, $ldapListRequest);


        foreach ($ldapGroupList as $ldapGroup) {
            if (gettype($ldapGroup) === 'array') { // first entry is the number of results!
                $group            = new \stdClass;
                $group->getTitle  = "ROLE_" . self::slugify($ldapGroup[$roleNameAttribute][0]);
                $group->getAsRole = "ROLE_" . self::slugify($ldapGroup[$roleNameAttribute][0]);
                $groups[]         = $group;
            }
        }

        //**//
        //$users  = $idProvider->searchLdapUsers($slug);
        return array('groups' => $groups, 'users' => $users);
    }

    /**
     * Used for delivering index page to start ldap search
     * @Route("/acl/search/", name="fom_user_acl_search_index")
     * @Method({ "GET" })
     * @Template("MapbenderLdapIntegrationBundle:ACL:ldap-search-form.html.twig")
     */
    public function searchIndexAction()
    {
        return array();
    }

    /**
     * @Route("/acl/overview", name="fom_user_acl_overview")
     * @Method({ "GET" })
     * @Template("MapbenderLdapIntegrationBundle:ACL:groups-and-users.html.twig")
     */
    public function overviewAction(){
        // $idProvider = $this->get('fom.identities.provider');
        // $groups = $idProvider->getAllGroups();
        // $users  = $idProvider->getAllUsers();
        // return array('groups' => $groups, 'users' => $users);
        return array();
    }

    /**
     * @Route("/acl/edit", name="fom_user_acl_edit")
     * @Method("GET")
     * @Template("MapbenderLdapIntegrationBundle:ACL:edit.html.twig")
     */
    public function editAction()
    {
        return parent::editAction();
    }

    private static function slugify($role)
    {
        $role = preg_replace('/\W+/u', '_', $role);
        $role = trim($role, '_');
        $role = mb_strtoupper($role, 'UTF-8');

        return $role;
    }
}

?>
