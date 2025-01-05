<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use App\Utils\Helper;
use Carbon\Carbon;

class SignIn extends Telegram {
    public $command = '/signin';
    public $description = '签到并获得流量奖励';

    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;
        
        if (!$message->is_private) return;
        
        // 获取用户信息
        $user = User::where('telegram_id', $message->chat_id)->first();
        
        if (!$user) {
            $telegramService->sendMessage($message->chat_id, '没有查询到您的用户信息，请先绑定账号', 'markdown');
            return;
        }

        // 获取当前日期
        $today = Carbon::today()->toDateString(); // 获取今天的日期，格式为 'YYYY-MM-DD'

        // 检查用户是否已经签到
        if ($user->last_sign_in === $today) {
            $telegramService->sendMessage($message->chat_id, '今天您已经签到过了，明天再来吧！', 'markdown');
            return;
        }

        // 随机生成1到10GB的流量奖励
        $randomTraffic = rand(1, 10) * 1024 * 1024 * 1024;  // 转换成字节 (1GB = 1024MB = 1024*1024KB = 1024*1024*1024字节)
        
        // 从已用下行流量中扣除这个流量
        $user->d = max(0, $user->d - $randomTraffic);  // 确保下行流量不为负数
        
        // 更新用户的签到日期为今天
        $user->last_sign_in = $today;

        // 保存更新后的用户数据
        if (!$user->save()) {
            $telegramService->sendMessage($message->chat_id, '签到失败，请稍后再试。', 'markdown');
            return;
        }
        
        // 返回签到信息
        $trafficAward = Helper::trafficConvert($randomTraffic); // 转换奖励流量为可读格式
        $text = "🎉签到成功🎉\n\n您获得了 `{$trafficAward}` 流量奖励！";
        $telegramService->sendMessage($message->chat_id, $text, 'markdown');
    }
}
