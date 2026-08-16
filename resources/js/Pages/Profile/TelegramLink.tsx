import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircleOutlined, LinkOutlined, SendOutlined } from '@ant-design/icons';
import { Alert, Button, Card, Space, Statistic, theme, Typography } from 'antd';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { PageProps } from '@/types';

interface TelegramLinkProps {
    botUsername: string;
    linkCode: string | null;
    linkCodeExpiresAt: string | null;
    isLinked: boolean;
    linkedAt: string | null;
}

export default function TelegramLink() {
    const { token } = theme.useToken();
    const { botUsername, linkCode, linkCodeExpiresAt, isLinked, linkedAt } =
        usePage<PageProps & TelegramLinkProps>().props;

    const [secondsLeft, setSecondsLeft] = useState<number | null>(null);

    useEffect(() => {
        if (!linkCodeExpiresAt) {
            setSecondsLeft(null);
            return;
        }

        const update = () => {
            const diff = Math.floor((new Date(linkCodeExpiresAt).getTime() - Date.now()) / 1000);
            setSecondsLeft(diff > 0 ? diff : 0);
        };

        update();
        const interval = setInterval(update, 1000);

        return () => clearInterval(interval);
    }, [linkCodeExpiresAt]);

    const generateCode = () => {
        router.post('/profile/telegram/generate-code');
    };

    const telegramDeepLink = linkCode
        ? `https://t.me/${botUsername}?start=${linkCode}`
        : `https://t.me/${botUsername}`;

    const formatCountdown = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    return (
        <AuthenticatedLayout title="Telegram Link">
            <Head title="Telegram Link" />

            <Card style={{ maxWidth: 560 }}>
                <Space direction="vertical" size="large" style={{ width: '100%' }}>
                    {isLinked && (
                        <Alert
                            type="success"
                            showIcon
                            icon={<CheckCircleOutlined />}
                            message="Account Linked"
                            description={
                                linkedAt
                                    ? `Your Telegram account was linked on ${new Date(linkedAt).toLocaleString()}.`
                                    : 'Your Telegram account is linked.'
                            }
                        />
                    )}

                    <div>
                        <Typography.Title level={5}>Bot</Typography.Title>
                        <Typography.Text code>@{botUsername}</Typography.Text>
                    </div>

                    {linkCode && secondsLeft !== null && secondsLeft > 0 && (
                        <Card size="small" style={{ background: token.colorSuccessBg, borderColor: token.colorSuccessBorder }}>
                            <Space direction="vertical" style={{ width: '100%' }}>
                                <Typography.Text strong>Your link code:</Typography.Text>
                                <Typography.Title level={2} style={{ margin: 0, letterSpacing: 8 }}>
                                    {linkCode}
                                </Typography.Title>
                                <Statistic.Countdown
                                    title="Expires in"
                                    value={Date.now() + secondsLeft * 1000}
                                    format="mm:ss"
                                />
                            </Space>
                        </Card>
                    )}

                    {linkCode && secondsLeft === 0 && (
                        <Alert type="warning" message="Link code has expired. Generate a new one." />
                    )}

                    <Space wrap>
                        <Button
                            type="primary"
                            icon={<LinkOutlined />}
                            onClick={generateCode}
                        >
                            Generate Link Code
                        </Button>
                        <Button
                            icon={<SendOutlined />}
                            href={telegramDeepLink}
                            target="_blank"
                        >
                            Open in Telegram
                        </Button>
                    </Space>

                    <Typography.Paragraph type="secondary">
                        1. Click <strong>Generate Link Code</strong> to create a 6-character code (valid 10 minutes).
                        <br />
                        2. Open the bot in Telegram and send <Typography.Text code>/link YOUR_CODE</Typography.Text>
                        <br />
                        3. Or use <strong>Open in Telegram</strong> to start the bot with your code pre-filled.
                    </Typography.Paragraph>
                </Space>
            </Card>
        </AuthenticatedLayout>
    );
}
