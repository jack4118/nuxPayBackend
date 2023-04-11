var conn = {
  mysql_conf: {
    host: "localhost",
    user: "root",
    password: "password",
    database: "database"
  },

  cryptoXmppUser: {
    jid: "crypto@dev.xun.global", // xmpp jid
    password: "password", // xmpp password
    host: "dev.thenux.com", // connection domain
    groupname: ""
  },

  host: "dev.thenux.com", // connection domain
  jid_host: "dev.xun.global" // xmpp jid domain
};

module.exports = {
  settings: conn
};
