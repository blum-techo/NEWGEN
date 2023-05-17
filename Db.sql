CREATE TABLE `newgen`.`author` ( `author_id` INT NOT NULL , 
								`name` VARCHAR(100) NOT NULL , 
                                `listeners` INT NOT NULL , 
                                `likes` INT NOT NULL , 
                                PRIMARY KEY (`author_id`));
                                
CREATE TABLE `newgen`.`track` ( `track_id` INT NOT NULL , 
								`name` VARCHAR(100) NOT NULL , 
                                `album` VARCHAR(100) NOT NULL , 
                                `duration` INT NOT NULL , 
                                `author_id` INT NOT NULL , 
                                PRIMARY KEY (`track_id`),
                                CONSTRAINT track_author_fk 
                                FOREIGN KEY (`author_id`)  REFERENCES `newgen`.`author` (`author_id`));