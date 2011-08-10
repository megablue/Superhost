CREATE TABLE `superhost_games_packet_log` (
`log_id` INT NOT NULL ,
`game_id` INT NOT NULL ,
`slot_index` TINYINT( 2 ) NOT NULL ,
`packet` TEXT NOT NULL
) ENGINE = MYISAM ;
