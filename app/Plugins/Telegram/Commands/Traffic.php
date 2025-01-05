<?php

namespace App\Plugins\Telegram\Commands;

use App\Models\User;
use App\Plugins\Telegram\Telegram;
use App\Utils\Helper;

class Traffic extends Telegram {
    public $command = '/traffic';
    public $description = '查询流量信息';

    public function handle($message, $match = []) {
        $telegramService = $this->telegramService;

        // 判断是否为私聊消息
        if (!$message->is_private) {
            return;
        }

        // 查找用户
        $user = $this->getUserByTelegramId($message->chat_id);
        if (!$user) {
            $telegramService->sendMessage($message->chat_id, '没有查询到您的用户信息，请先绑定账号', 'markdown');
            return;
        }

        // 获取并计算流量信息
        $trafficInfo = $this->getTrafficInfo($user);

        // 获取到期时间
        $expiryStatus = $this->getExpiryStatus($user->expired_at);

        // 构建响应消息
        $responseText = $this->buildResponseText($trafficInfo, $expiryStatus);

        // 发送流量信息
        $telegramService->sendMessage($message->chat_id, $responseText, 'markdown');
    }

    /**
     * 根据 Telegram 用户ID 获取用户信息
     */
    private function getUserByTelegramId($chatId) {
        return User::where('telegram_id', $chatId)->first();
    }

    /**
     * 获取用户的流量信息
     */
    private function getTrafficInfo($user) {
        // 进行数值验证，避免无效值
        $transferEnable = $this->getValidTraffic($user->transfer_enable);
        $uploaded = $this->getValidTraffic($user->u);
        $downloaded = $this->getValidTraffic($user->d);
        
        // 获取已用流量
        $used = $uploaded + $downloaded;
        
        // 获取剩余流量
        $remaining = $this->getValidTraffic($user->transfer_enable - $used);

        return [
            'transferEnable' => Helper::trafficConvert($transferEnable),
            'used' => Helper::trafficConvert($used),
            'remaining' => Helper::trafficConvert($remaining)
        ];
    }

    /**
     * 获取有效的流量数据，避免无效值
     */
    private function getValidTraffic($value) {
        // 如果值为空或不是数字，则返回0
        return is_numeric($value) ? (float) $value : 0;
    }

    /**
     * 获取流量到期状态
     */
    private function getExpiryStatus($expiredAt) {
        // 当前时间戳
        $currentTime = time();

        // 如果没有到期时间，则为长期有效
        if ($expiredAt == 0) {
            return "长期有效";
        }

        // 判断是否已过期
        if ($expiredAt < $currentTime) {
            return "已到期";
        }

        // 返回格式化的到期时间
        return date("Y-m-d H:i:s", $expiredAt);
    }

    /**
     * 构建响应的流量查询文本
     */
    private function buildResponseText($trafficInfo, $expiryStatus) {
        return "🚥流量查询🚥\n" .
            "计划流量：`{$trafficInfo['transferEnable']}`\n" .
            "已用流量：`{$trafficInfo['used']}`\n" .
            "剩余流量：`{$trafficInfo['remaining']}`\n" .
            "到期时间：`{$expiryStatus}`\n";
    }
}

namespace App\Utils;

class Helper {
    /**
     * 流量单位转换，转换为 GB 或 MB，保留两位小数
     * @param int $bytes 流量（字节数）
     * @return string 转换后的流量值，带单位（GB 或 MB）
     */
    public static function trafficConvert($bytes) {
        if (!is_numeric($bytes) || $bytes < 0) {
            return '0MB';  // 防止无效数据
        }

        // 转换为GB
        $gigabytes = $bytes / (1024 * 1024 * 1024);

        if ($gigabytes >= 1) {
            // 如果大于等于1GB，显示为GB，保留两位小数
            return number_format($gigabytes, 2) . 'GB';
        }

        // 否则，转换为MB，保留两位小数
        $megabytes = $bytes / (1024 * 1024);
        return number_format($megabytes, 2) . 'MB';
    }
}
