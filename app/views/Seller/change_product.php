<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>產品資料更改</title>
    <link rel="stylesheet" href="/CoverShopping/public/css/Modules.css">
    <script language="javascript" src="/CoverShopping/public/js/Seller.js"></script>
    <style>
        /* 只限本頁表格內輸入欄位靠左 */
        table.seller-management td input,
        table.seller-management td textarea,
        table.seller-management td select {
            text-align: left;
            display: inline-block;
        }
    </style>
</head>

<body>
    <form name="myForm" style="text-align:center" method="post" enctype="multipart/form-data">
        <table class="seller-management" border="1" rules="all" align="center">
            <caption><b>
                    <h2>產品ID:<?= htmlspecialchars($product->id) ?></h2>
                </b></caption>
            <tr class="header">
                <th>資料屬性</th>
                <th>更新資料</th>
            </tr>
            <tr class="data">
                <td>產品封面</td>
                <td><img src="/CoverShopping/public/images/<?= htmlspecialchars(basename($product->image)) ?>" width='100' height='140' alt="未上傳圖片"><br>
                    <input type="file" name="image" accept="image/*">
                </td>
            </tr>
            <tr class="data">
                <td>產品名稱</td>
                <td><input type="text" name="name" value="<?= htmlspecialchars($product->name) ?>"></td>
            </tr>
            <tr class="data">
                <td>產品介紹</td>
                <td><textarea name="content" cols="50" rows="5"><?= htmlspecialchars($product->content) ?></textarea></td>
            </tr>
            <tr class="data">
                <td>商品價格</td>
                <td><input type="number" name="price" value="<?= htmlspecialchars($product->price) ?>"></td>
            </tr>
            <tr class="data">
                <td>產品存量</td>
                <td><input type="number" name="inventory" value="<?= htmlspecialchars($product->inventory) ?>"></td>
            </tr>
        </table>
        <input type="button" class="btn" value="儲存" onclick="save('<?= htmlspecialchars($product->id) ?>','<?= htmlspecialchars($product->image) ?>')">
        <input type="reset" class="btn" value="重新輸入">
    </form>
    <p align="center"><a href="/CoverShopping/routes/web.php?page=management_product">商品管理頁面</a></p>
</body>

</html>