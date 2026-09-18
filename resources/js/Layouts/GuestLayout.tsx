import type { ReactNode } from 'react';
import { Carousel, Grid, theme, Typography } from 'antd';

export interface ResortSlide {
    src: string;
    alt?: string;
}

interface GuestLayoutProps {
    children: ReactNode;
    slides?: ResortSlide[];
}

export default function GuestLayout({ children, slides = [] }: GuestLayoutProps) {
    const { token } = theme.useToken();
    const screens = Grid.useBreakpoint();
    const showHero = slides.length > 0 && screens.lg;

    return (
        <>
            <style>{`
                .guest-layout {
                    display: flex;
                    min-height: 100vh;
                    width: 100%;
                }
                .guest-layout__hero {
                    flex: 1 1 60%;
                    position: relative;
                    overflow: hidden;
                }
                .guest-layout__form {
                    flex: 1 1 40%;
                    min-width: 0;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 16px;
                    box-sizing: border-box;
                }
                @media (min-width: 992px) {
                    .guest-layout__form {
                        min-width: 420px;
                        flex: 0 0 40%;
                        padding: 24px;
                    }
                }
                .guest-carousel,
                .guest-carousel .slick-slider,
                .guest-carousel .slick-list,
                .guest-carousel .slick-track,
                .guest-carousel .slick-slide,
                .guest-carousel .slick-slide > div {
                    height: 100%;
                }
                .guest-carousel .slick-slide > div {
                    height: 100vh;
                }
                .guest-carousel__image {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    display: block;
                }
            `}</style>
            <div className="guest-layout" style={{ background: token.colorBgLayout }}>
                {showHero && (
                    <div className="guest-layout__hero">
                        <Carousel
                            className="guest-carousel"
                            autoplay
                            arrows
                            dots
                            speed={1400}
                            autoplaySpeed={6000}
                            style={{ height: '100vh' }}
                        >
                            {slides.map((slide) => (
                                <div key={slide.src}>
                                    <img
                                        className="guest-carousel__image"
                                        src={slide.src}
                                        alt={slide.alt ?? 'Pratasaba Resort'}
                                    />
                                </div>
                            ))}
                        </Carousel>
                        <div
                            style={{
                                position: 'absolute',
                                inset: 0,
                                background:
                                    'linear-gradient(to top, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.15) 50%, rgba(0,0,0,0.35) 100%)',
                                pointerEvents: 'none',
                            }}
                        />
                        <div
                            style={{
                                position: 'absolute',
                                bottom: 48,
                                left: 48,
                                right: 48,
                                pointerEvents: 'none',
                            }}
                        >
                            <Typography.Title
                                level={2}
                                style={{ color: '#fff', margin: 0, marginBottom: 8 }}
                            >
                                Pratasaba Resort
                            </Typography.Title>
                            <Typography.Paragraph
                                style={{
                                    color: 'rgba(255,255,255,0.85)',
                                    margin: 0,
                                    fontSize: 16,
                                }}
                            >
                                Dive Resort and Spa, Maratua
                            </Typography.Paragraph>
                        </div>
                    </div>
                )}
                <div className="guest-layout__form">
                    <div style={{ width: '100%', maxWidth: 420, boxSizing: 'border-box' }}>
                        {children}
                    </div>
                </div>
            </div>
        </>
    );
}
