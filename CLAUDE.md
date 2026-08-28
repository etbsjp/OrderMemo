# OrderMemo

etbs が配布する WordPress プラグイン。共通ルールの正本は `~/.claude/etbs-plugin-rules.md`。

## レビュー工程に大（シニアエンジニア）を追加する

このリポジトリでは、安藤（`vk-code-reviewer`）のレビューのあと、**PR を作成する前に**
大（`etbs-senior-wp`）の監査を必ず通すこと。大は etbs の申し送りと過去に踏んだ罠に照らして
「リリースできる形になっているか」を見る担当で、安藤の一般的なコード品質レビューとは層が違う。

- `Agent` ツールで `subagent_type: etbs-senior-wp`、`name: etbs-senior-wp`、
  **`run_in_background: false`** で起動する
- **`isolation: "worktree"` は使えるなら付ける**（付けないと起動応答は「成功」と返るのに
  一度も作業せず待機状態に入ることがある）。ただし ★★ **作業ディレクトリが git リポジトリでないと
  使えない**。その場合は **isolation なしで起動してよい**（isolation なしでも正常に完走した実績あり）。
  「必須」ではない。**見分け方は起動応答の形**——`output_file` 付きの正常形なら動いている
- prompt には対象リポジトリ・ブランチ・差分（または PR 番号）を渡す
- 大には **出力の末尾に `監査結果: PASS` または `監査結果: FAIL` を必ず書くよう指示する**
  （★ 大の定義ファイルには出力形式の指定が無いため、指示しないと合否を機械判定できない）
- `監査結果: PASS` を受け取るまで PR を作成しない。`FAIL` なら和田へ差し戻して再監査する

★ 大は vk-agents のメンバー表に登録されていないため、指示が無いと**永久に呼ばれない**。

## 検証環境

Local の `order-memo`（`ordermemo.etbs.lc`）。このプラグインは `dirname( __FILE__ )` を
1階層のみ（同一ディレクトリの `inc/func.php` の require）に使っており `dirname( __FILE__, N )`
の複数階層遡りは無いため、**シンボリックリンク設置でよい**。

★ このサイトは sigusa.jp のクローンで**実顧客の注文データ**を含む。氏名・メール・住所を出力しないこと。
また `order-memo` サイトには woo-hit-orderlist 等、他の WooCommerce 依存プラグインも同居しているため、
**このプラグイン以外のフォルダを変更しない**。

CLI 検証では Local の php.ini を `-c` で渡すこと。渡さないと「データベース接続確立エラー」になり、
**サイトが停止しているように見える**（実際は動いている）。`<runId>` は
`ls -d ~/Library/Application\ Support/Local/run/*/mysql/mysqld.sock` で特定する。

## アンインストール

★ `uninstall.php` の方針は**案A**（task-queue #108）。判定は3分類。

| 利用者が作ったコンテンツ（投稿・投稿メタ） | 利用者が設定した値（オプション） | 一時状態・自分が仕掛けた cron |
|---|---|---|
| **消さない** | **消さない** | **消す** |

理由は害の非対称性。消さないことの害は「DB に少量のレコードが残る」だけだが、消すことの害は
復旧不可能。迷ったら残す側に倒す。

このプラグインでの当てはめ:

- **残す** … CPT `ormm_template` の投稿（利用者が作った定型文）
- **消す** … 該当なし（独自テーブルも cron も持たない）

★ 1.0.2 までは `wp_delete_post( $id, true )` で `ormm_template` を全件、ゴミ箱を経由せず完全削除
していた。1.0.3 で撤去済み。**削除ロジックを足し直さないこと。**

★ 配布8本すべてがこの3分類で説明できる状態にしてある。テーブルと cron を持つのは editlock だけ、
一時状態のオプションを持つのは pageguard だけで、そこだけが「消す」に該当する。
**他のプラグインで「何も消していない」のは判断の結果であって書き忘れではない。**
横並びで「消す」側へ揃えにこないこと。

## 版数

版数の置き場は `ordermemo.php` の `Version:` ヘッダの1箇所（`readme.txt` は無い）。

```sh
grep -n "^ \* Version:" ordermemo.php
```

★ **`ORMM_VERSION` 定数（`ordermemo.php:18`）はヘッダと別に手で書かれており、既に不一致
（ヘッダ 1.0.1 に対して `1.0.0` のまま）。** `inc/func.php:226` でスクリプト/スタイルの
キャッシュバスターとして使われている。**版数を上げる際はこの定数も必ず揃えること**
（揃え忘れても動作は壊れないが、更新後にブラウザキャッシュが残る）。

## 配布物

`dist` ブランチへのマージ＝配信。PUC が配る zip には**追跡しているファイルが全部入る**ため、
`.gitignore`（追跡させない）と `.gitattributes` の `export-ignore`（zip から落とす）は役割が別。
両方を維持すること。

## CI（2026-08-28 導入）

**共通ルールは `~/.claude/etbs-plugin-rules.md` の 2.7 節**（standard の選定理由・`phpcbf` を走らせない理由・
third-party action をタグ固定にしている判断・陽性対照・配布物の検証手順など）。
**そちらの内容はここに転記しない**（二重管理になり、必ず片方が古びる）。
ここに置くのは **このリポジトリでしか決まらない値**だけ。

- 定義は `.github/workflows/ci.yml`。PR ごとに `php -l`（PHP 7.4 / 8.3）と
  `PHPCS (WordPress-Extra, changed lines)` が走る。`dist` への直 push では `php -l` の2つだけ走る
- **既存指摘の基準値: 47 ERROR / 7 WARNING**（2026-08-28 実測・`WordPress-Extra`）。
  ★ 測り直すときは `vendor/bin/phpcs --standard=./.phpcs.xml.dist --report=summary $(git ls-files '*.php')`
  の形でのみ行う。素の phpcs は `.gitignore` を尊重しない
- **`Requires PHP: 7.4` を宣言している。** 置き場は `ordermemo.php` の1箇所だけ（`readme.txt` は無い）。
  CI の matrix `['7.4','8.3']` と**一致している状態を守ること**（片方だけ動かさない）
- **`composer.json` の `name` は原本のまま**（`etbsjp/widget-shortcode-tools`）。
  `composer.lock` の `content-hash` と**ペアとして整合している**ので、これでよい。
  ★ 改名するなら `composer update --lock` も必ず走らせること
- `.github/workflows/ci.yml` は**原本と byte 一致**。書き換えない（直すなら原本側で直して再展開）

## 宣言（Requires）の方針

★★ `Requires at least`（WP）は**実測した下限があるときだけ書く。無ければ書かない。**
`Requires PHP` は実測下限ではなく **「etbs が動作を保証する最低 PHP」の宣言として 7.4 を書く**。
**この2つは過剰宣言したときの害の向きが逆なので、同じ基準で扱わない。他のプラグインと横並びで揃えない。**

| | 過剰に宣言すると | 過小に宣言すると |
|---|---|---|
| `Requires at least`（WP） | **有効化・更新が拒否される**＝修正が届かない個体を作る | 古い WP に入るが、使う API が無ければその場で分かる |
| `Requires PHP` | 入れられる環境が狭まるだけ | 構文エラーで白画面。しかも FTP 手動設置は止められない |

- このリポジトリは `Requires at least: 6.7` を**削除**した（task-queue #88）。
  理由：ブロックを登録しておらず（`block.json` / `register_block_type` / `registerBlockType` は0件）、
  自前コードの最も新しい WP API が `sanitize_textarea_field()`（WP 4.7）、
  同梱 PUC を含めても `wp_doing_cron()`（WP 4.8）で、**6.x 帯に下限が存在しない**。
  6.7 は初版からの定型文で、特定の API に紐づいたものではなかった
- `Requires PHP: 7.4` は**据え置き**。★ これは実測下限ではない。「7.4 で `php -l` が通る」ことは
  7.4 で*足りる*証明であって *必要*である証明ではなく、7.3 以下は未検証。
  そのうえで保守方針として 7.4 を宣言している。次に見た人が「下限じゃないなら消せる」と
  判断しないよう、この理由を残しておく
- `Requires Plugins: woocommerce`（WP 6.5+ で解釈）は据え置き。6.4 以下ではこのヘッダは
  無言で無視されるが、`inc/func.php` に `class_exists( 'WooCommerce' )` の実行時ガードと
  管理画面通知があり、`wc_get_order()` の呼び出し箇所もガード済みのため、
  `Requires at least` を削除しても安全（woo-hit-orderlist で確立した判断を踏襲）

★ `README.md` の「必要環境」にもヘッダと同じ情報を書いている（利用者向けの説明のため）。
**ヘッダの `Requires at least` / `Requires PHP` を変更したときは、`README.md` の該当箇所も
同時に見直すこと。** 揃えないまま放置すると、次に見た人がどちらが正しいか分からず、
README に合わせてヘッダへ過剰宣言を書き戻す方向に動きかねない。

★★ **「据え置き」と「新規に足す」は別問題**（2026-08-25 / task-queue #111 で再確認）。
既に宣言している版を据え置いても新たに締め出す個体は生まれないが、**無宣言のプラグインに
`Requires PHP` を新しく足すと、いま更新が届いている個体を以後届かなくする**。
`woo-checkout-colorbox` と `widget-shortcode-tools` が無宣言なのは、この理由による意図的な判断。
**8本で揃えにこないこと。**
