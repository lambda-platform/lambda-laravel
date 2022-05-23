const app = require('express')();
const server = require('http').Server(app);
const io = require('socket.io')(server);

server.listen(3000, '0.0.0.0');

app.get('/', function (req, res) {
    res.sendFile(__dirname + '/index.html');
});

io.on('connection', function (socket) {

    socket.on('notify', function (msgObj) {
        console.log('working emit');
        io.emit('notify', msgObj)
    });
});
