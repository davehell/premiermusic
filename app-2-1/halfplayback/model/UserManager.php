<?php

use Nette\Security,
	Nette\Utils\Strings;


/**
 * Users authenticator.
 */
class UserManager extends Nette\Object implements Security\IAuthenticator
{
	const
		TABLE_NAME = 'uzivatel',
		COLUMN_ID = 'id',
		COLUMN_NAME = 'login',
		COLUMN_PASSWORD = 'heslo',
    COLUMN_SALT = 'salt',
		PASSWORD_MAX_LENGTH = 1024;

	/** @var Nette\Database\Context */
	private $database;


	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}


	/**
	 * Performs an authentication.
	 * @param  array
	 * @return Nette\Security\Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials)
	{
		list($username, $password) = $credentials;
		$row = $this->database->table(self::TABLE_NAME)->where(self::COLUMN_NAME, $username)->fetch();

		if (!$row) {
			throw new Security\AuthenticationException('Nesprávné uživatelské jméno nebo heslo.', self::IDENTITY_NOT_FOUND);
		}

		if ($row->heslo !== $this->generateHash($password, $row->salt)) {
			throw new Security\AuthenticationException('Nesprávné uživatelské jméno nebo heslo.', self::INVALID_CREDENTIAL);
		}

		$arr = $row->toArray();
    $roles = Array();
    $roles[] = $arr['role'];
    if($arr['role'] == 'admin') $roles[] = 'spravce';

		unset($arr[self::COLUMN_PASSWORD]);
    unset($arr[self::COLUMN_SALT]);
    unset($arr['role']);
    unset($arr['heslo_token']);
    unset($arr['heslo_token_platnost']);
		return new Security\Identity($row->id, $roles, $arr);
	}


	/**
	 * Computes salted password hash.
	 * @return string
	 */
	public static function generateHash($password, $salt)
	{
		return sha1($password.$salt);
	}

}
