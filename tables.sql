CREATE TABLE IF NOT EXISTS external_revs (
  er_id INT UNSIGNED NOT NULL PRIMARY KEY,
  er_text MEDIUMTEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS diffs (
  diff_oldid INT UNSIGNED NOT NULL,
  diff_newid INT UNSIGNED NOT NULL,
  diff_text1 MEDIUMBLOB NOT NULL,
  diff_text2 MEDIUMBLOB NOT NULL,
  diff_time1 INT UNSIGNED NOT NULL,
  diff_time2 INT UNSIGNED NOT NULL,
  diff_random FLOAT NOT NULL,

  PRIMARY KEY( diff_oldid, diff_newid )
);

CREATE INDEX diff_random ON diffs (diff_random);

CREATE TABLE IF NOT EXISTS diff_votes (
  dv_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT NOT NULL,
  dv_oldid INT UNSIGNED NOT NULL,
  dv_newid INT UNSIGNED NOT NULL,
  dv_user VARBINARY(255) NOT NULL,
  dv_winner INT NOT NULL,
  dv_timestamp VARCHAR(14),

  KEY winner(dv_winner)
);
