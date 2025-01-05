<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;

class Search extends Telegram {
    public $command = '/search';
    public $description = '根据邮箱模糊查询用户信息';

    public function handle($message, $match = []) {
        // 确保是私聊消息
        if (!$message->is_private) return;

        // 获取当前用户的 Telegram 信息（使用 chat_id 判断）
        $user = User::where('telegram_id', $message->chat_id)->first();

        // 如果用户不存在或不是管理员，返回提示消息并终止
        if (!$user || !$user->is_admin) {
            $telegramService = $this->telegramService;
            $telegramService->sendMessage($message->chat_id, '您没有权限执行此操作');
            return;
        }

        // 检查是否提供了邮箱参数
        if (!isset($message->args[0])) {
            $telegramService = $this->telegramService;
            $telegramService->sendMessage($message->chat_id, '参数有误，请携带邮箱地址发送');
            return;
        }

        // 获取邮箱参数
        $email = $message->args[0];

        // 模糊查询用户，支持部分匹配
        $users = User::where('email', 'like', '%' . $email . '%')->get();

        // 如果没有找到匹配的用户
        if ($users->isEmpty()) {
            $telegramService = $this->telegramService;
            $telegramService->sendMessage($message->chat_id, '没有找到匹配的用户');
            return;
        }

        // 如果找到匹配的用户，生成返回的文本信息
        $text = "🔍 用户查询结果🔍\n";
        foreach ($users as $matchedUser) {
            // 获取流量参数并转换为GB
            $transferEnable = $matchedUser->transfer_enable / (1024 * 1024 * 1024); // 转换为GB
            $up = $matchedUser->u / (1024 * 1024 * 1024); // 转换为GB
            $down = $matchedUser->d / (1024 * 1024 * 1024); // 转换为GB
            $used = $up + $down; // 上行 + 下行
            $remaining = $transferEnable - $used; // 剩余流量
            $expiredtime = $matchedUser->expired_at;
            // 格式化为保留两位小数
            $transferEnable = number_format($transferEnable, 2);
            $up = number_format($up, 2);
            $down = number_format($down, 2);
            $used = number_format($used, 2);
            $remaining = number_format($remaining, 2);
            #$dateline=date("Y-m-d H:i:s", $expiredtime);
                // 判断时间戳是否为0，如果是0则显示“长期有效”
            if ($expiredtime == 0) {
                $dateline = "长期有效";
            } else {
                // 将时间戳转换为日期格式
                $dateline = date("Y-m-d H:i:s", $expiredtime);
            }
            // 添加到查询结果文本
            $text .= "邮箱：{$matchedUser->email}\n";
            $text .= "套餐流量：{$transferEnable} GB\n";
            $text .= "已用流量：{$used} GB\n";
            $text .= "剩余流量：{$remaining} GB\n";
            $text .= "到期时间：{$dateline}\n\n";  // 每个用户信息之间空一行
        }

        // 返回结果
        $telegramService = $this->telegramService;
        $telegramService->sendMessage($message->chat_id, $text, 'markdown');
    }
}
