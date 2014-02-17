<?php
App::uses('AppModel', 'Model');
/**
 * GuideDispReport Model
 *
 */
class GuideDispReport extends AppModel {


	public $validate = array(
	);

	/**
	* レポート記事情報取得
	*
	* @param array $_wa_data パラメタ情報
	* @return  array 詳細情報
	*/
	public function getDispReportListApi($_wa_data){

		$_ws_query = null;
		$_ws_guide = null;
		$_ws_limit = null;

		if (isset($_wa_data['mode'])){
			$_ws_mode = pg_escape_string($_wa_data['mode']);
			if ($_ws_mode === "hotel"){
				$_wi_mode = 1;
			} else if($_ws_mode === "gourmet"){
				$_wi_mode = 2;
//			} else if($_ws_mode === "shop"){
//				$_wi_mode = 3;
			} else if($_ws_mode === "sightseeing"){
				$_wi_mode = 4;
//			} else if($_ws_mode === "optional"){
//				$_wi_mode = 5;
			} else {
				return false;
			}
			$_ws_query .= "AND spot.spot_large_category_id[1] = {$_wi_mode}";
		} else {
			// 指定がない場合はデフォルトが観光
			$_ws_query .= "AND spot.spot_large_category_id[1] = 4";
		}
		// 都市ＩＤは必須
		$_ws_city_id = pg_escape_string($_wa_data['city_id']);
		$_ws_query .= " AND spot.city_code[1] = '{$_ws_city_id}'";
		// プレビュー
		if (!isset($_wa_data['preview'])){
			$_ws_query .=" AND report.guide_report_open < now()";
		}

		$_ws_sql = <<<EOT
 SELECT DISTINCT
	prof.id,
	prof.name,
	prof.nickname,
	prof.name_display_status,
	prof.photo_id,
	prof.sex,
	prof.age_division,
	prof.job_name,
	(SELECT guide_report_catch_copy FROM guide_disp_reports WHERE guide_profile_id = prof.id AND category_id={$_wi_mode} LIMIT 1) AS guide_report_catch_copy,
	(SELECT guide_report_updated FROM guide_disp_reports WHERE guide_profile_id = prof.id LIMIT 1) AS guide_report_updated
 FROM
	guide_disp_reports report,
	guide_profiles prof,
	wspots spot,
	spot_categories cate
 WHERE
	 report.guide_profile_id = prof.id
 AND report.guide_report_del_flg = false
 AND report.body_del_flg = false
 AND report.wspot_del_flg = false
 AND report.guide_report_status = 0
 AND report.body_status = 0
 AND report.wspot_status = 0
 AND spot.id = report.wspot_id
 AND spot.spot_large_category_id[1] = report.category_id
 AND cate.id = spot.spot_large_category_id[1]
 {$_ws_query}
 ORDER BY guide_report_updated DESC
 LIMIT 3
EOT;
echo $_ws_sql;exit;
		$result['prof'] = $this->query($_ws_sql, false);

		if (count($result['prof']) == 0){ return null; }

		$i = 0;
		foreach ($result['prof'] as $_wa_guide){

			// 選択ガイドごとの作成データ
			$_ws_sql = <<<EOT
 SELECT
	report.wspot_tag_info_arr
 FROM
	guide_disp_reports report,
	guide_profiles prof,
	wspots spot,
	spot_categories cate
 WHERE
	report.guide_profile_id = prof.id
 AND report.guide_report_del_flg = false
 AND report.body_del_flg = false
 AND report.wspot_del_flg = false
 AND report.guide_report_status = 0
 AND report.body_status = 0
 AND report.wspot_status = 0
 AND spot.id = report.wspot_id
 AND spot.spot_large_category_id[1] = report.category_id
 AND cate.id = spot.spot_large_category_id[1]
 AND report.guide_profile_id = {$_wa_guide[0]['id']}
{$_ws_query}
ORDER BY wspot_tag_info_arr
EOT;
			$result[$i]['prof_report'] = $this->query($_ws_sql, false);
			$i++;
		}

		// 記事情報を取得するのは一人分だけ
		foreach ($result['prof'] as $_wa_guide){
			// ガイド指定パラメタがあればチェック
			if (isset($_wa_data['guide_id'])){
				if ($_wa_guide[0]['id'] !== (int)$_wa_data['guide_id']){
					continue;
				}
			}
			// ガイド指定のない場合は最初のひとり目を表示
			$_ws_guide = " AND report.guide_profile_id = {$_wa_guide[0]['id']}"; // 表示するガイドID確定

			// Reportタイトル部取得
			$_ws_sql = <<<EOT
SELECT DISTINCT
	report.guide_profile_id,
	report.guide_report_id,
	report.title,
	report.category_id,
	spot.city_code[1] AS city_code,
	spot.area_code[1] AS area_code
FROM
	guide_disp_reports report,
	guide_profiles prof,
	wspots spot,
	spot_categories cate
WHERE
	report.guide_profile_id = prof.id
AND report.guide_report_del_flg = false
AND report.body_del_flg = false
AND report.wspot_del_flg = false
AND report.guide_report_status = 0
AND report.body_status = 0
AND report.wspot_status = 0
AND spot.id = report.wspot_id
AND spot.spot_large_category_id[1] = report.category_id
AND cate.id = spot.spot_large_category_id[1]
 {$_ws_guide}
 {$_ws_query}
EOT;

			$result['title'] = $this->query($_ws_sql, false);
			break;	// 一人分取得出来て終了W
		} // foreach
		if($result === false){
			return false;
		}else{
			return $result;
		}
	}

}
