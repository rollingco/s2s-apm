  <div class="panel">
    <div class="h">🧪 Test presets (by identifier)</div>
    <div class="kv">Виберіть сценарій: підставимо потрібний <code>identifier</code> і коректний SLE <code>payer_phone</code>.</div><br>
    <?php
      // два офіційні пресети з документації
      $presets = [
        'SALE → SETTLED'  => ['id' => 'success@gmail.com', 'phone' => '23233310905'],
        'SALE → DECLINED' => ['id' => 'fail@gmail.com',    'phone' => '23233310905'],
      ];
      $baseParams = [
        'brand'  => $brand,
        'ccy'    => $order_ccy,
        'amt'    => $order_amt,
        'return' => $return_url,
      ];
      foreach ($presets as $label => $p) {
        $url = $self.'?'.http_build_query(array_merge($baseParams, [
          'id'    => $p['id'],
          'phone' => $p['phone'],
        ]));
        echo '<a class="btn" href="'.h($url).'" target="_blank">'.h($label).' (id='.$p['id'].')</a> ';
      }
    ?>

    <hr style="border:0;border-top:1px solid #2a2f3a;margin:14px 0">

    <div class="h">Custom run</div>
    <form action="<?=h($self)?>" method="get" style="margin-top:8px">
      <?php foreach ($baseParams as $k=>$v): ?>
        <input type="hidden" name="<?=h($k)?>" value="<?=h($v)?>">
      <?php endforeach; ?>
      <label>identifier:
        <input type="text" name="id" value="<?=h($identifier)?>">
      </label>
      <span style="display:inline-block;width:10px"></span>
      <label>payer_phone (SLE):
        <input type="text" name="phone" value="<?=h($payer_phone)?>">
      </label>
      <button class="btn" type="submit" style="margin-left:10px">Send SALE</button>
    </form>
  </div>
