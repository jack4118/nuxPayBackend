#!/bin/bash
DATABASE=dbname
MYSQL_USER=root
MYSQL_PASS=password
MYSQL_CONN="-u${MYSQL_USER} -p${MYSQL_PASS}"
SQL="SET group_concat_max_len = 102400;"
SQL="${SQL} SELECT GROUP_CONCAT(CONCAT('--ignore-table=${DATABASE}.',table_name) SEPARATOR ' ')"
SQL="${SQL} FROM information_schema.tables WHERE table_schema='${DATABASE}'"
SQL="${SQL} AND (table_name LIKE 'web_services_%'  or table_name LIKE 'sent_history_%' or table_name LIKE 'acc_closingBK%')"
EXCLUSION_LIST=`mysql ${MYSQL_CONN} -AN -e"${SQL}"`
echo "Dumping database..."
echo "Excluded tables: ${EXCLUSION_LIST}"
DATE=`date "+%Y%m%d_%H%M%S"`
mysqldump ${MYSQL_CONN} ${DATABASE} ${EXCLUSION_LIST} > ${DATABASE}_${DATE}.sql