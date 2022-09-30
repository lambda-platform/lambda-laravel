<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset password</title>
    <style>
        *{
            color: #484848 !important;
        }

        body {
            padding: 30px 20px;
            font-size: 14px;
            background: #eeeeee;
        }

        p {
            background: #ffffff !important;
            padding: 20px;
            color: #484848 !important;
        }

        img {
            display: block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<p>
    <img src="http://mle.mn/assets/mle/images/copyright.png" alt="mle" height="60px">
    Сайн байна уу?<br/>
    Та дараах холбоосоор орж нууц үгээ сэргээнэ үү.<br/>
    <b><a href="http://mle.mn/#/reset/{{$email}}/{{$token}}">нууц үг сэргээх холбоос</a></b><br/><br>
    Баярлалаа.
</p>
</body>
</html>
