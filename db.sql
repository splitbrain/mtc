CREATE DATABASE `mtc` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `mtc_comments` (
`page` VARCHAR( 32 ) NOT NULL ,
`id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT,
`name` VARCHAR( 255 ) NOT NULL ,
`mail` VARCHAR( 255 ) NOT NULL ,
`date` DATETIME NOT NULL ,
`text` TEXT NOT NULL ,
`ip` VARCHAR( 255 ) NOT NULL ,
`url` VARCHAR( 255 ) NOT NULL ,
PRIMARY KEY ( `page`, `id` )
) TYPE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;


-- Added 2006-10-20 to avoid replay attacks
ALTER TABLE `mtc_comments` ADD `captcha` VARCHAR( 32 ) NOT NULL ;
UPDATE `mtc_comments` SET captcha = MD5( RAND( ) ) ;
ALTER TABLE `mtc_comments` ADD UNIQUE ( `captcha` ) ;
